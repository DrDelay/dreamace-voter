<?php
/**
 * This file is part of drdelay/dreamace-voter.
 *
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\DreamAceVoter\Console;

use DrDelay\DreamAceVoter\Console\Command\AutoVoteCommand;
use DrDelay\DreamAceVoter\Voter;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct()
    {
        error_reporting(-1);
        parent::__construct('DreamACE Voter', Voter::VERSION);
        $this->add(new AutoVoteCommand());
    }

    public function getLongVersion()
    {
        return parent::getLongVersion().' by <comment>DrDelay</comment>';
    }
}
