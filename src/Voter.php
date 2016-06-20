<?php

/*
 * This file is part of drdelay/dreamace-voter.
 *
 * (c) DrDelay <info@vi0lation.de>
 *
 * This source file is subject to the MIT license that is bundled with this source code in the file LICENSE.
 */

/**
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\DreamAceVoter;

use Faker\Factory as FakerFactory;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class Voter
{
    const VERSION = '1.0.1';
    const DA_WEBSITE = 'http://dreamace.org';

    const DELAY_MIN = 2000;
    const DELAY_MAX = 5000;
    const ACC_PAGE = '/index.php?site=account';
    const IPTABLES_COMMAND = '/sbin/iptables';
    const IPTABLES_BLOCKMODE = 'REJECT';

    /** @var LoggerInterface */
    protected $logger;
    /** @var bool */
    protected $debug;

    /** @var Client */
    protected $client;

    /** @var string */
    protected $username;
    /** @var string */
    protected $password;
    /** @var int */
    protected $char_id;

    /** @var bool */
    protected $fast = false;
    /** @var array|null */
    protected $close_ports;

    /** @var array */
    protected $closedRules = array();

    /**
     * Constructor.
     *
     * @param string          $username
     * @param string          $password
     * @param int             $char_id
     * @param string|null     $fake_agent If none is specified one is obtained via Faker
     * @param LoggerInterface $logger
     * @param bool|false      $debug
     */
    public function __construct(
        string $username,
        string $password,
        int $char_id,
        string $fake_agent = null,
        LoggerInterface $logger,
        bool $debug = false
    ) {
        $this->logger = $logger;
        $this->debug = $debug;

        $this->username = $username;
        $this->password = $password;
        $this->char_id = $char_id;

        if (!$fake_agent) {
            $fake_agent = FakerFactory::create()->userAgent;
        }

        $this->client = new Client([
            'base_uri' => self::DA_WEBSITE,
            'timeout' => 20.0,
            'cookies' => true,
            'headers' => [
                'User-Agent' => $fake_agent,
            ],
            'debug' => $this->debug,
        ]);
    }

    /**
     * Set whether to skip delays or not.
     *
     * @param bool $fast
     *
     * @return $this
     */
    public function setFast(bool $fast)
    {
        $this->fast = $fast;

        return $this;
    }

    /**
     * Set ports to close when voting.
     *
     * @param array $ports
     *
     * @return $this
     */
    public function setClose_ports(array $ports)
    {
        $this->close_ports = $ports;

        return $this;
    }

    /**
     * Close the ports.
     */
    protected function closePorts()
    {
        if (is_array($this->close_ports)) {
            foreach ($this->close_ports as $port) {
                $rule = 'INPUT -p tcp --destination-port '.$port.' -j '.self::IPTABLES_BLOCKMODE;
                $blockRule = self::IPTABLES_COMMAND.' -A '.$rule;
                $this->closedRules[] = $rule;
                Utils::safeExec($blockRule, $this->logger);
            }
        }
    }

    /**
     * Remove the close-rules.
     */
    public function reopenPorts()
    {
        foreach ($this->closedRules as $key => $rule) {
            $unblockRule = self::IPTABLES_COMMAND.' -D '.$rule;
            Utils::safeExec($unblockRule, $this->logger);
            unset($this->closedRules[$key]);
        }
    }

    /**
     * The real things happen here.
     *
     * @param bool|false $noVotesDump Dump the response if no votes are found
     *
     * @return int Votes successfully done
     *
     * @throws VoterException In case of errors
     */
    public function autovote(bool $noVotesDump = false)
    {
        // Sending the login request without visiting the login page would be too obvious
        $this->client->get(self::ACC_PAGE);
        $this->delay();

        $this->logger->info('Logging in');
        $this->client->post(self::ACC_PAGE, array(
            'form_params' => [
                'login_id' => $this->username,
                'login_pw' => $this->password,
                'login_submit' => 'Log in',
            ],
        ));

        $this->delay();
        // The webpage somehow also reloads once more after login, we want to mimic that behaviour
        $this->logger->info('Check login');
        $accountResponseBody = Utils::responseBody($this->client->get(self::ACC_PAGE));
        if (empty($accountResponseBody)) {
            throw new VoterException('Login check: Empty response');
        }
        if (!Utils::strContains($accountResponseBody, 'You are logged in as')) {
            if ($this->debug) {
                dump($accountResponseBody);
            }
            throw new VoterException('Login failed: Incorrect login data / logged in to game ('.$this->username.':'.$this->password.')');
        }

        $this->delay();
        $this->logger->info('Init voting');
        $this->closePorts();
        $voteInitResponseBody = Utils::responseBody($this->client->post('/index.php?site=account&a=vote', array(
            'form_params' => [
                'chose_character' => (string) $this->char_id,
                'reset_submit' => '',
            ],
        )));
        $this->reopenPorts();

        $this->logger->info('Extracting vote IDs / checking vote count');
        if (empty($voteInitResponseBody)) {
            throw new VoterException('Vote init: Empty response');
        }
        if (!preg_match_all('/vote_topsite\((\d+)\)/', $voteInitResponseBody, $matches,
                PREG_PATTERN_ORDER) || empty($matches[1])
        ) {
            if (Utils::strContains($voteInitResponseBody, 'You have been detected to be using a proxy')) {
                throw new VoterException('Rejected because of proxy-check, see README.md');
            }
            if ($noVotesDump) {
                dump($voteInitResponseBody);
            }
            throw new VoterException('No possible votes found: On cooldown?');
        }

        $voteCount = sizeof($matches[1]);
        $this->logger->notice('Found '.$voteCount.' possible votes: '.implode(', ', $matches[1]));

        foreach ($matches[1] as $match) {
            $this->delay();
            $voteResponse = Utils::responseBody($this->client->post('ajax/vote.php', array(
                'form_params' => [
                    'vote_num' => $match,
                ],
            )));
            if (empty($voteResponse)) {
                throw new VoterException('Vote '.$match.': Empty vote response');
            }
            $voteResponseInt = (int) $voteResponse;
            if ($voteResponseInt == 2) {
                $this->logger->notice('Vote '.$match.' gave WP');
            } elseif ($voteResponseInt == 3) {
                $this->logger->notice('Vote '.$match.' gave Donate-P');
            } else {
                $this->logger->error('Vote '.$match.' failed. Response: '.$voteResponse);
                --$voteCount;
            }
        }

        return $voteCount;
    }

    /**
     * Delay if not in fast mode.
     */
    protected function delay()
    {
        if (!$this->fast) {
            Utils::randDelay(self::DELAY_MIN, self::DELAY_MAX, $this->logger);
        }
    }
}
