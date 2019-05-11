<?php

namespace Reposer;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The "quick-update" command class.
 *
 * Generate a new composer.lock if we can do so quickly.
 */
class QuickUpdateCommand extends BaseCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    parent::configure();
    $this
      ->setName('quick-update')
      ->setDescription('Quickly generate a new lockfile.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $handler = new Handler($this->getComposer(), $this->getIO());
    $handler->scaffold();
  }

}
