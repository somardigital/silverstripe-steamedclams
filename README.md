# Introduction

Using ClamAV, this module scans files as they're uploaded and denies the upload if a virus was detected. If the ClamAV
daemon is down it will log that the file needs to be scanned, wherein you can either manually scan via the CMS once the
daemon is back online, run a nightly cron that scans the files, or if you have queuedjobs installed, it will
automatically scan missed files nightly.

# Requirements

- Silverstripe CMS 6
- PHP 8.3 or higher, with the `sockets` extension enabled
- ClamAV daemon (`clamd`) running on the same server, listening on a local socket
- (optional) [silverstripe/queuedjobs](https://github.com/symbiote/silverstripe-queuedjobs) to automatically re-scan
  files that were missed while the daemon was offline

# Composer Install

```
composer require symbiote/silverstripe-steamedclams
```

# Screenshots

![ModelAdmin](https://cloud.githubusercontent.com/assets/3859574/20911711/000abbd2-bbbe-11e6-9b93-f0490cc055f7.png)

![UploadField](https://cloud.githubusercontent.com/assets/3859574/20907335/b8459310-bba1-11e6-86d2-3a5f6cc6e959.jpg)

# Quick Start

1) Install ClamAV in Unix/Linux.
```
sudo apt-get update
sudo apt install clamav clamav-daemon
```

2) Start clamav-daemon
```
sudo service clamav-freshclam restart
# wait ~2 minutes
sudo service clamav-daemon start
```
And check the clamav-daemon is running.
```
sudo service clamav-daemon status
```

3) Setup socket permissions.
The clamav-daemon creates `/var/run/clamav/clamd.ctl` if it doesn't exist. Make sure the user and group your web server
and CLI PHP run as can access it, replacing `user:group` below:
```
sudo mkdir /var/run/clamav
sudo chown -R user:group /var/run/clamav
```

4) Configure clamd.conf:
```
# Path to a local socket file the daemon will listen on.
# Default: disabled (must be specified by a user)
LocalSocket /var/run/clamav/clamd.ctl
```

(optional) You can use a different socket path, but you will need to change it in
your YML config to match your clamd.conf:
```yml
Symbiote\SteamedClams\ClamAV:
  clamd:
    LocalSocket: '/var/run/clamav/clamd.ctl'
```

5) Build the database and flush the cache:
```
vendor/bin/sake db:build --flush
```
or visit `http://{mysite.com}/dev/build?flush=1`. All files will now be scanned for viruses automatically when they're
uploaded / validated.

6) To check it's running properly, it should show that it's ONLINE at: `http://{mysite.com}/admin/clamav`

# Usage

Once installed, the module provides the following in the CMS:

- **ClamAV admin** (`/admin/clamav`): shows whether the daemon is ONLINE or OFFLINE, how many files are still waiting
  to be scanned, and a log of every scan. Unscanned files can be scanned from here once the daemon is back online, and
  infected or unscanned files can be ignored.
- **Report** (`/admin/reports`): "Clam AV Scan results" lets you filter scans by date, scan status, action taken and
  uploading member, and export the results to CSV.
- **Settings** (`/admin/settings`): the "ClamAV" tab lets you change the validation message shown to the user when a
  virus is detected (defaults to "A virus was detected.").

# Configuration

```yml
Symbiote\SteamedClams\ClamAV:
  # Make this the same as your clamd.conf settings
  clamd:
    LocalSocket: '/var/run/clamav/clamd.ctl'
  # If true and the ClamAV daemon isn't running or isn't installed the file will be denied as if it has a virus.
  deny_on_failure: false
  # For configuring on existing site builds and ignoring the scanning of pre-module install `File` records.
  initial_scan_ignore_before_datetime: '1970-12-25 00:00:00'
```

If you have the QueuedJobs module installed, you can configure when files missed by the ClamAV daemon are scanned.
This job will only queue if the daemon couldn't be connected to at the time that the file was uploaded.

```yml
Symbiote\SteamedClams\Jobs\ClamAVScanJob:
  # This job will queue itself on dev/build by default if `File` records have been missed in scanning.
  disable_queue_on_devbuild: false
  # Repeat daily by default (in seconds).
  repeat_time: 86400
  # Repeat at 2am by default
  time: '02:00:00'
```

# Tasks

Both tasks can be run from the command line with `sake`, or in the browser at the URL shown.

## Install on existing project

By running the task below, all files uploaded before installation of the module will be scanned.

```
vendor/bin/sake tasks:Symbiote-SteamedClams-Tasks-ClamAVInstallTask
```
or visit `http://{mysite.com}/dev/tasks/Symbiote-SteamedClams-Tasks-ClamAVInstallTask`

To ignore files created before a specific date, you can configure the datetime in your YML config, as below:

```yml
Symbiote\SteamedClams\ClamAV:
  initial_scan_ignore_before_datetime: '2015-06-06 00:00:00'
```

## Scan missed files

If you don't have QueuedJobs installed, run the task below (e.g. as a nightly cron job) to scan files that were
uploaded while the ClamAV daemon was offline.

```
vendor/bin/sake tasks:Symbiote-SteamedClams-Tasks-ClamAVScanTask
```
or visit `http://{mysite.com}/dev/tasks/Symbiote-SteamedClams-Tasks-ClamAVScanTask`

# Emulate Mode

To emulate ClamAV results without a running ClamAV daemon, put in your YML.
Restricting it to the `dev` environment avoids accidentally disabling virus scanning on a live site.

```yml
---
Name: clamav-emulator
Only:
  environment: dev
---
SilverStripe\Core\Injector\Injector:
  Symbiote\SteamedClams\ClamAV:
    class: Symbiote\SteamedClams\ClamAVEmulator
Symbiote\SteamedClams\ClamAVEmulator:
  # 1 = no virus found, 2 = virus found (Eicar-Test-Signature), 3 = ClamAV daemon offline
  mode: 1
```

Alternatively, for quicker testing, use your `app/_config.php` to switch between the various testing modes:

```php
<?php

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Symbiote\SteamedClams\ClamAV;
use Symbiote\SteamedClams\ClamAVEmulator;

// Use this instead of YAML for quicker testing
Config::modify()->merge(Injector::class, ClamAV::class, ['class' => ClamAVEmulator::class]);

// If no virus found
ClamAVEmulator::config()->set('mode', ClamAVEmulator::MODE_NO_VIRUS);

// If virus found (Eicar-Test-Signature)
//ClamAVEmulator::config()->set('mode', ClamAVEmulator::MODE_HAS_VIRUS);

// If ClamAV daemon isn't running
//ClamAVEmulator::config()->set('mode', ClamAVEmulator::MODE_OFFLINE);
```

Run `vendor/bin/sake db:build --flush` (or visit `dev/build?flush=1`) after changing either. When emulating,
`/admin/clamav` will report the emulated version (`ClamAV 0.99.2/...`) as ONLINE, or OFFLINE in offline mode.

# Supports
- Silverstripe 6 (this version)
- For Silverstripe 4 and 5, use the earlier releases of this module
- For Silverstripe 3.2 and up (3.1 *should* work, create an issue if you determine otherwise) use 1.0
- [CDN Content](https://github.com/symbiote/silverstripe-cdncontent) (missing local files are downloaded before
  scanning)

# Credits
[Barakat S](https://github.com/FileZ/php-clamd) for clamd PHP interface
["How to Forge" users](https://web.archive.org/web/20161124000346/https://www.howtoforge.com/community/threads/clamd-will-not-start.34559/) for fixing permission issues
