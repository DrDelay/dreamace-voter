<?php
/**
 * This file is part of drdelay/dreamace-voter.
 *
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\DreamAceVoter;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Utils
{
    /**
     * Delays execution by some time to make it not that obvious it's a script.
     *
     * @param int                  $min    Should be >= 0
     * @param int                  $max
     * @param OutputInterface|null $output Writes an info message if set
     */
    public static function randDelay(int $min, int $max, OutputInterface $output = null)
    {
        $delay = mt_rand($min, $max);
        if ($output && $output->isVeryVerbose()) {
            $output->writeln('<info>Delay for '.$delay.'ms</info>');
        }
        usleep($delay * 1000);
    }

    /**
     * Print a verbose message.
     *
     * @param OutputInterface $output
     * @param $message
     */
    public static function verbosePrint(OutputInterface $output, $message)
    {
        if ($output->isVerbose()) {
            $output->writeln($message);
        }
    }

    /**
     * Get a trimmed response body.
     *
     * @param ResponseInterface $response
     *
     * @return string
     */
    public static function responseBody(ResponseInterface $response)
    {
        return trim((string) $response->getBody());
    }

    /**
     * Checks whether $needle is in $haystack.
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    public static function strContains(string $haystack, string $needle)
    {
        return strpos($haystack, $needle) !== false;
    }
}
