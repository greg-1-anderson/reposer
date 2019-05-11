## Goal

Provide a new tool to replace ‘composer update’ and ‘composer require'

### Premise

Composer update usually returns the most recent available version of each dependency that matches the version constraint in the top level composer.json file (or whichever project it is first seen in). We should be able to achieve this same result in a fraction of the time.

## Operation

The tool will be packaged as a Composer installer that provides replacement versions for `composer update` and `composer require`.

### Basic resolver algorithm:

1. For each dependency (require / require-dev as appropriate) in composer.json:
  - Fetch all available versions for each dependency
  - Take best version from constraints and record it
  - Add dependency to the “process list”
1. For each item in the “process list”
  - For each dependencies of the dependency (at the recorded version):
  - FAIL if the new dependency has a recorded version that does not match the constraint from the entry in the composer.json file that is being processed. Failure message suggests adding an exception to top-level composer.json.
  - If the new dependency does not have a recorded version, then process it per step 1 (fetch available versions, select the best one, record it, add item to the process list)
1. Repeat step 2 until the process list is empty

### Exceptions list:

- The top-level composer.json (only) may have an exceptions list
- Items in the exceptions list are treated like require / require-dev items
- The exceptions list is processed first, before require / require-dev
- The exceptions list is only documentation that these version constraints only exist to resolve problems caused by dependency constraints that are incompatible with the desires of the top-level project.

### Parent projects:

- The top-level composer.json (only) may declare exactly one dependency to be the “parent project”
- Before the basic resolver is started, the composer.lock from the parent project is loaded.
- The exact version of each dependency in that composer.lock is recorded.
- The basic resolver then continues as usual
- Optional: if directed, the require-dev from the parent project is added to the require-dev list from the top-level composer.json file

### Stretch goals:

- Use the existing Packagist API to retrieve project version metadata.
  - This should probably work. We can’t get composer.lock data from Packagist, but we can get dependency lists w/ version constraints for all versions of any given projects easily.
  - This will probably give us way too much data, as packagist always gives us all of the versions every time we ask for a project. Will be interesting to see if we still can update with much less memory than `composer update`
- Write a composer.lock file that is compatible with the existing Composer lock file format.
- Install via ‘composer install’

### Security updates:

- How can we identify project versions that are security updates?
- If this is possible, we should consider a “security update only” feature.
