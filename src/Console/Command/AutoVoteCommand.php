<?php
/**
 * This file is part of drdelay/dreamace-voter.
 *
 * @author DrDelay <info@vi0lation.de>
 */

namespace DrDelay\DreamAceVoter\Console\Command;

use DrDelay\DreamAceVoter\Voter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AutoVoteCommand extends Command
{
    protected function configure()
    {
        $this->setName('autovote')
            ->setDefinition(array(
                new InputArgument('username', InputArgument::REQUIRED, 'The DreamACE username', null),
                new InputArgument('password', InputArgument::REQUIRED, 'The DreamACE password', null),
                new InputArgument('char_id', InputArgument::REQUIRED, 'The DreamACE character ID', null),
                new InputArgument('user_agent', InputArgument::OPTIONAL, 'A custom User-Agent', null),
                new InputOption('fast', '', InputOption::VALUE_NONE, 'Skip "human delays"'),
            ))
            ->setDescription('Runs the autovoting')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command runs this tool.
It logs in to your account on the DreamACE website, gets available votes and does them.

    <info>%command.full_name% johnny secr3t 1337</info>

The optional <comment>user_agent</comment> argument lets you define a custom "Fake-User-Agent". If none is set the tool just randomly chooses one.

The <comment>--fast</comment> option skips "human delays".
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        set_time_limit(0);

        $voter = new Voter(
            $input->getArgument('username'),
            $input->getArgument('password'),
            (int) $input->getArgument('char_id'),
            $input->getArgument('user_agent')
        );
        $voter->setFast((bool) $input->getOption('fast'));

        $result = $voter->autovote($output);

        $output->writeln('<info>'.$result.' successful votes</info>');

        return $result > 0 ? 0 : 1;
    }
}
