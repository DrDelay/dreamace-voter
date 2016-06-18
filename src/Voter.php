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
    const VERSION = '1.0.0';
    const DA_WEBSITE = 'http://dreamace.org';

    const DELAY_MIN = 2000;
    const DELAY_MAX = 5000;

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

    /**
     * Constructor.
     *
     * @param string      $username
     * @param string      $password
     * @param int         $char_id
     * @param string|null $fake_agent If none is specified one is obtained with Faker
     */
    public function __construct(string $username, string $password, int $char_id, string $fake_agent = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->char_id = $char_id;

        if (is_null($fake_agent)) {
            $fake_agent = FakerFactory::create()->userAgent;
        }

        $this->client = new Client([
            'base_uri' => self::DA_WEBSITE,
            'timeout' => 20.0,
            'cookies' => true,
            'headers' => [
                'User-Agent' => $fake_agent,
            ],
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
        Utils::verbosePrint($output, '<comment>Logging in</comment>');
        $this->client->post('/index.php?site=account', array(
            'form_params' => [
                'login_id' => $this->username,
                'login_pw' => $this->password,
                'login_submit' => 'Log in',
            ],
        ));

        $this->delay($output);
        Utils::verbosePrint($output, '<comment>Init voting</comment>');
        $voteInitResponseBody = Utils::responseBody($this->client->post('/index.php?site=account&a=vote', array(
            'form_params' => [
                'chose_character' => (string) $this->char_id,
                'reset_submit' => '',
            ],
        )));

        Utils::verbosePrint($output, '<comment>Extracting vote IDs / checking vote count</comment>');
        if (empty($voteInitResponseBody) || !preg_match_all('/vote_topsite\((\d+)\)/', $voteInitResponseBody, $matches,
                PREG_PATTERN_ORDER) || empty($matches[1])
        ) {
            throw new VoterException('No possible votes found: On cooldown / incorrect login data / logged in to game');
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
