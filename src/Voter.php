<?php
/**
 * This file is part of drdelay/dreamace-voter.
 *
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\DreamAceVoter;

use Faker\Factory as FakerFactory;
use GuzzleHttp\Client;
use Symfony\Component\Console\Output\OutputInterface;

class Voter
{
    const VERSION = '1.0.1';
    const DA_WEBSITE = 'http://dreamace.org';

    const DELAY_MIN = 2000;
    const DELAY_MAX = 5000;
    const ACC_PAGE = '/index.php?site=account';
    const IPTABLES_COMMAND = 'iptables';
    const IPTABLES_BLOCKMODE = 'REJECT';

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
    /** @var int[]|null */
    protected $close_ports;

    /** @var array|null */
    protected $closedRules;

    /**
     * Constructor.
     *
     * @param string      $username
     * @param string      $password
     * @param int         $char_id
     * @param string|null $fake_agent If none is specified one is obtained with Faker
     * @param bool|false  $debug      Whether guzzle should be verbose
     */
    public function __construct(
        string $username,
        string $password,
        int $char_id,
        string $fake_agent = null,
        bool $debug = false
    ) {
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
            'debug' => $debug,
        ]);

        $this->closedRules = array();
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
     *
     * @param OutputInterface $output
     */
    protected function closePorts(OutputInterface $output)
    {
        if (is_array($this->close_ports)) {
            foreach ($this->close_ports as $port) {
                $rule = 'INPUT -p tcp --destination-port '.$port.' -j '.self::IPTABLES_BLOCKMODE;
                $blockRule = self::IPTABLES_COMMAND.' -A '.$rule;
                $this->closedRules[] = $rule;
                Utils::verbosePrint($output, '<comment>'.$blockRule.'</comment>');
                Utils::safeSilentExec($blockRule);
            }
        }
    }

    /**
     * Remove the close-rules.
     *
     * @param OutputInterface $output
     */
    public function reopenPorts(OutputInterface $output)
    {
        foreach ($this->closedRules as $key => $rule) {
            $unblockRule = self::IPTABLES_COMMAND.' -D '.$rule;
            Utils::safeSilentExec($unblockRule);
            Utils::verbosePrint($output, '<comment>'.$unblockRule.'</comment>');
            unset($this->closedRules[$key]);
        }
    }

    /**
     * The real things happen here.
     *
     * @param OutputInterface $output
     *
     * @return int Votes successfully done
     *
     * @throws VoterException In case of errors
     */
    public function autovote(OutputInterface $output)
    {
        // Sending the login request without visiting the login page would be too obvious
        $this->client->get(self::ACC_PAGE);
        $this->delay($output);

        Utils::verbosePrint($output, '<comment>Logging in</comment>');
        $this->client->post(self::ACC_PAGE, array(
            'form_params' => [
                'login_id' => $this->username,
                'login_pw' => $this->password,
                'login_submit' => 'Log in',
            ],
        ));

        $this->delay($output);
        // The webpage somehow also reloads once more after login, we want to mimic that behaviour
        Utils::verbosePrint($output, '<comment>Check login</comment>');
        $accountResponseBody = Utils::responseBody($this->client->get(self::ACC_PAGE));
        if (empty($accountResponseBody)) {
            throw new VoterException('Login check: Empty response');
        }
        if (!Utils::strContains($accountResponseBody, 'You are logged in as')) {
            if ($output->isDebug()) {
                dump($accountResponseBody);
            }
            throw new VoterException('Login failed: Incorrect login data / logged in to game ('.$this->username.':'.$this->password.')');
        }
        Utils::verbosePrint($output, '<comment>Login seems to be good</comment>');

        $this->delay($output);
        Utils::verbosePrint($output, '<comment>Init voting</comment>');
        $this->closePorts($output);
        $voteInitResponseBody = Utils::responseBody($this->client->post('/index.php?site=account&a=vote', array(
            'form_params' => [
                'chose_character' => (string) $this->char_id,
                'reset_submit' => '',
            ],
        )));
        $this->reopenPorts($output);

        Utils::verbosePrint($output, '<comment>Extracting vote IDs / checking vote count</comment>');
        if (empty($voteInitResponseBody)) {
            throw new VoterException('Vote init: Empty response');
        }
        if (!preg_match_all('/vote_topsite\((\d+)\)/', $voteInitResponseBody, $matches,
                PREG_PATTERN_ORDER) || empty($matches[1])
        ) {
            if (Utils::strContains($voteInitResponseBody, 'You have been detected to be using a proxy')) {
                throw new VoterException('Rejected because of proxy-check, see README.md');
            }
            if ($output->isDebug()) {
                dump($voteInitResponseBody);
            }
            throw new VoterException('No possible votes found: On cooldown?');
        }

        $voteCount = sizeof($matches[1]);
        $output->writeln('<info>Found '.$voteCount.' possible votes: '.implode(', ', $matches[1]).'</info>');

        Utils::verbosePrint($output, '<comment>Starting voting process</comment>');
        foreach ($matches[1] as $match) {
            $this->delay($output);
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
                $output->writeln('<info>Vote '.$match.' gave WP</info>');
            } elseif ($voteResponseInt == 3) {
                $output->writeln('<info>Vote '.$match.' gave Donate-P</info>');
            } else {
                $output->writeln('<error>Vote '.$match.' failed. Response: '.$voteResponse.'</error>');
                --$voteCount;
            }
        }

        return $voteCount;
    }

    /**
     * Delay if not in fast mode.
     *
     * @param OutputInterface $output Writes an info message
     */
    protected function delay(OutputInterface $output)
    {
        if (!$this->fast) {
            Utils::randDelay(self::DELAY_MIN, self::DELAY_MAX, $output);
        }
    }
}
