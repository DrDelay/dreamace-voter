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

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class Utils
{
    /**
     * Delays execution by some time to make it not that obvious it's a script.
     *
     * @param int                  $min    Should be >= 0
     * @param int                  $max
     * @param LoggerInterface|null $logger Writes an info message if set
     */
    public static function randDelay(int $min, int $max, LoggerInterface $logger = null)
    {
        $delay = mt_rand($min, $max);
        if ($logger) {
            $logger->info('Delay for '.$delay.'ms');
        }
        usleep($delay * 1000);
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

    /**
     * Executes a command checking the return code.
     *
     * @param string               $command
     * @param LoggerInterface|null $logger  Writes an warning message if set
     *
     * @throws \Exception
     */
    public static function safeExec(string $command, LoggerInterface $logger = null)
    {
        if ($logger) {
            $logger->warning('[exec] '.$command);
        }
        exec($command, $out, $code);
        if ($code !== 0) {
            throw new \Exception('Exec of "'.$command.'" failed');
        }
    }
}
