## Goal

Provide a new command `composer resolve` to replace `composer update` and `composer require`.

We aim to be able to update composer lock files (not including download time) using time and memory similar to what is required today fto run `composer install`.

### Status

Proposal only; no code yet.

### Usage

<table>
  <tr>
    <th>Current Way</th>
    <th>Prototype Equivalent</th>
  </tr>  
  <tr>
    <td><pre>$ composer update
    </pre>
    </td>
    <td><pre>$ composer resolve
$ composer install</pre>
    </td>
  </tr>
  <tr>
    <td><pre>$ composer require foo&#x2F;bar
    
</pre>
    </td>
    <td><pre>$ composer require --no-update foo&#x2F;bar
$ composer resolve
$ composer install</pre>
</td>
  </tr>
</table>

If the prototype proves to be useful, then a better DX could be provided.

### Premise

Composer update usually returns the most recent available version of each dependency that matches the version constraint in the top level composer.json file (or whichever project it is first seen in). We should be able to achieve this same result in a fraction of the time with modest memory requirements.

The theory is that a simple algorithm that takes the best matching version from the version constraints specified the first time a given dependency is seen should result in a correct resolution most of the time. If it does not, then maintainers may place exceptions in the top-level composer.json file to help resolve any conflicts. Error messages from failed resolutions may provide hints to help users do this correctly.

The commonly-held belief is that the current solver used in Composer today is necessary in order to to achieve a good set of dependencies. However, even the current implementation has shortcomings, and it is presently very difficult for beginners to work around some of the problems that may be encountered. This prototype aims to discover whether a the more restrictive set of limitations proposed here might still provide an equivalent usability experience with much better performance.

### Prototype

This project provides an experimental command "composer resolve" that aims to create a composer.lock file using a minimum of time and resources. If a lock file cannot be resolved, then an error is printed. The user must add exceptions to the top-level composer.json file to avoid conflicts.

### Alternative

Composer 2 is investigating whether the current solver may continue to be used to produce similar results with less effort by [aggressively pruning the data fed into the solver](https://github.com/composer/composer/issues/3672). If their investigation is successful, then the prototype proposed here will not be necessary. If pruning the dependency tree only produces linear improvements on the exponential algorithm, though, then its gains might eventually be overshadowed as the number of dependency-versions used in projects increase over time. This experiment is a contingency against that possibility.

### Proof of Usefulness

At this point it is unknown what the results will be. Once we have some working code we can take measurements from existing data available in Packagist.

## Operation

The tool will be packaged as a Composer plugin that provides a `composer resolve` command to replace `composer update`. A more efficient `composer require` may be effected by running the existing command with the `--no-update` flag, and then running `composer resolve`.

The `composer resolve` command always ignores whatever is in the existing lock file and vendor directory, and creates a new lock file from scratch every time. No dependencies are ever downloaded. Run `composer install` to download dependencies.

### Basic resolver algorithm:

- For each dependency (require / require-dev as appropriate) in composer.json:
  - Fetch all available versions for each dependency
  - Take best version from constraints and record it
  - Add dependency to the “process list”
- For each item in the “process list”
  - For each dependencies of the dependency (at the recorded version):
  - FAIL if the new dependency has a recorded version that does not match the constraint from the entry in the composer.json file that is being processed. Failure message suggests adding an exception to top-level composer.json.
  - If the new dependency does not have a recorded version, then process it per step 1 (fetch available versions, select the best one, record it, add item to the process list)
- Repeat step 2 until the process list is empty

### Other Considerations

- Use the existing Packagist API to retrieve project version metadata.
  - This should probably work. We can’t get composer.lock data from Packagist, but we can get dependency lists w/ version constraints for all versions of any given projects easily.
  - This will probably give us way too much data, as packagist always gives us all of the versions every time we ask for a project. Will be interesting to see if we still can update with much less memory than `composer update`
  - We can avoid replacing Packagist for our prototype by providing a simple pass-through "filtering" server that sits in front of Packagist. e.g. we can ask our prototype server to fetch us information only about version x.y.z; the prototype server could in turn ask Packagist for all of the data on the current project and pass through only the requested version, so our prototype tool only sees the data (and spends memory on) the one version it is interested in.
- Write a composer.lock file that is compatible with the existing Composer lock file format.
- Install via ‘composer install’

## Future Features

It might not be possible to implement all of the items described below without changing the implementation of `composer install`. The items below are other things that are of interest.

### Partial Updates

The current version of Composer allows partial updates, e.g. `composer update drupal/core --with-dependencies`. The first version of this prototype will only do full updates of all dependencies in the project. If initial results look promising, then partial updates, with and without the `--with-dependencies` flag, will be added.

### Exceptions List

Purpose: make it easier to "clean up" rules added only to resolve conflicts after said rules are no longer necessary.

- The top-level composer.json (only) may have an exceptions list
- Items in the exceptions list are treated like require / require-dev items
- The exceptions list is processed first, before require / require-dev
- The exceptions list is only documentation that these version constraints only exist to resolve problems caused by dependency constraints that are incompatible with the desires of the top-level project.

### Parent Projects

Purpose: provide native support for the capabilities provided by webflo/drupal-core-strict and webflo/drupal-dev-dependencies without requiring the maintenance of parallel projects that make it harder to update.

- The top-level composer.json (only) may declare exactly one dependency to be the “parent project”
- Before the basic resolver is started, the composer.lock from the parent project is loaded.
- The exact version of each dependency in that composer.lock is recorded.
- The basic resolver then continues as usual
- Optional: if directed, the require-dev from the parent project is added to the require-dev list from the top-level composer.json file

### Security Updates

- How can we identify project versions that are security updates?
- If this is possible, we should consider a “security update only” feature.
