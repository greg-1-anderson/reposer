<?php

namespace Reposer;

use Composer\Script\Event;
use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin for handling drupal scaffold.
 */
class Plugin implements PluginInterface, EventSubscriberInterface, Capable {

  /**
   * The Composer Scaffold handler.
   *
   * @var \Reposer\Handler
   */
  protected $handler;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    // We use a separate PluginScripts object. This way we separate
    // functionality and also avoid some debug issues with the plugin being
    // copied on initialisation.
    // @see \Composer\Plugin\PluginManager::registerPackage()
    $this->handler = new Handler($composer, $io);
  }

  /**
   * {@inheritdoc}
   */
  public function getCapabilities() {
    return [
      'Composer\Plugin\Capability\CommandProvider' => 'Reposer\CommandProvider',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ScriptEvents::POST_UPDATE_CMD => 'postCmd',
    ];
  }

  /**
   * Post command event callback.
   *
   * @param \Composer\Script\Event $event
   *   The Composer event.
   */
  public function postCmd(Event $event) {
    $this->handler->onPostCmdEvent($event);
  }

}
