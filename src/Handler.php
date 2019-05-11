<?php

declare(strict_types = 1);

namespace Reposer;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem;

/**
 * Core class of the plugin.
 */
class Handler {

  /**
   * The Composer service.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * Composer's I/O service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * Handler constructor.
   *
   * @param \Composer\Composer $composer
   *   The Composer service.
   * @param \Composer\IO\IOInterface $io
   *   The Composer I/O service.
   */
  public function __construct(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * Post install command event.
   *
   * @param \Composer\Script\Event $event
   *   The Composer event.
   */
  public function onPostCmdEvent(Event $event) {
  }

  /**
   * Get the path to the 'vendor' directory.
   *
   * @return string
   *   The file path of the vendor directory.
   */
  public function getVendorPath() : string {
    $vendorDir = $this->composer->getConfig()->get('vendor-dir');
    $filesystem = new Filesystem();
    $filesystem->ensureDirectoryExists($vendorDir);
    return $filesystem->normalizePath(realpath($vendorDir));
  }

  /**
   * Retrieve a package from the current composer process.
   *
   * @param string $name
   *   Name of the package to get from the current composer installation.
   *
   * @return \Composer\Package\PackageInterface|null
   *   The Composer package.
   */
  protected function getPackage(string $name) {
    return $this->composer->getRepositoryManager()->getLocalRepository()->findPackage($name, '*');
  }

  /**
   * Retrieve options from optional "extra" configuration.
   *
   * @return array
   *   The composer-scaffold configuration array.
   */
  protected function getOptions() : array {
    return $this->getOptionsForPackage($this->composer->getPackage());
  }

  /**
   * Retrieve options from optional "extra" configuration for a package.
   *
   * @param \Composer\Package\PackageInterface $package
   *   The package to pull configuration options from.
   *
   * @return array
   *   The composer-scaffold configuration array for the given package.
   */
  protected function getOptionsForPackage(PackageInterface $package) : array {
    $extra = $package->getExtra() + ['reposer' => []];

    return $extra['reposer'];
  }

  /**
   * GetLocationReplacements creates an interpolator for the 'locations' element.
   *
   * The interpolator returned will replace a path string with the tokens
   * defined in the 'locations' element.
   *
   * Note that only the root package may define locations.
   *
   * @return Interpolator
   *   Object that will do replacements in a string using tokens in 'locations' element.
   */
  public function getLocationReplacements() : Interpolator {
    $interpolator = new Interpolator();

    $fs = new Filesystem();
    $options = $this->getOptions();
    $locations = $options['locations'] + ['web_root' => './'];
    $locations = array_map(
      function ($location) use ($fs) {
        $fs->ensureDirectoryExists($location);
        $location = realpath($location);
        return $location;
      },
      $locations
    );

    return $interpolator->setData($locations);
  }

  /**
   * Gets a consolidated list of file mappings from all allowed packages.
   *
   * @param \Composer\Package\Package[] $allowed_packages
   *   A multidimensional array of file mappings, as returned by
   *   self::getAllowedPackages().
   *
   * @return \Grasmash\ComposerScaffold\Operations\OperationInterface[]
   *   An array of destination paths => scaffold operation objects.
   */
  protected function getFileMappingsFromPackages(array $allowed_packages) : array {
    $file_mappings = [];
    foreach ($allowed_packages as $package_name => $package) {
      $package_file_mappings = $this->getPackageFileMappings($package);
      $file_mappings[$package_name] = $package_file_mappings;
    }
    return $file_mappings;
  }

  /**
   * Gets a list of all packages that are allowed to copy scaffold files.
   *
   * Configuration for packages specified later will override configuration
   * specified by packages listed earlier. In other words, the last listed
   * package has the highest priority. The root package will always be returned
   * at the end of the list.
   *
   * @return \Composer\Package\PackageInterface[]
   *   An array of allowed Composer packages.
   */
  protected function getAllowedPackages(): array {
    $options = $this->getOptions() + [
      'allowed-packages' => [],
    ];
    $allowed_packages = $this->recursiveGetAllowedPackages($options['allowed-packages']);

    // If the root package defines any file mappings, then implicitly add it
    // to the list of allowed packages. Add it at the end so that it overrides
    // all the preceding packages.
    if (!empty($options['file-mapping'])) {
      $root_package = $this->composer->getPackage();
      unset($allowed_packages[$root_package->getName()]);
      $allowed_packages[$root_package->getName()] = $root_package;
    }

    return $allowed_packages;
  }

  /**
   * Get the root package name.
   *
   * @return string
   *   The package name of the root project
   */
  protected function rootPackageName() : string {
    $root_package = $this->composer->getPackage();
    return $root_package->getName();
  }

}
