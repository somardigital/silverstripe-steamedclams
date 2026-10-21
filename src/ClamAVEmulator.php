<?php

namespace Symbiote\SteamedClams;

use ClamdSocketException;
use LogicException;
use SilverStripe\Core\Config\Config;

/**
 * For emulating/faking ClamAV results
 *
 * This was implemented so Windows users and inexperienced developers can
 * focus on the logic surrounding the ClamAV daemon, without needing to
 * know how install it.
 */
class ClamAVEmulator extends ClamAV
{
    const MODE_UNKNOWN = 0;
    const MODE_NO_VIRUS = 1;
    const MODE_HAS_VIRUS = 2;
    const MODE_OFFLINE = 3;

    /**
     * The state of ClamAV to fake
     *
     * @var int
     */
    private static $mode = self::MODE_UNKNOWN;

    /**
     * The version string to return when emulating.
     *
     * @var string
     */
    private static $emulate_version = 'ClamAV 0.99.2/22585/Wed Nov 23 00:21:08 2016';

    /**
     * {@inheritDoc}
     */
    public function version()
    {
        $mode = Config::inst()->get(self::class, 'mode');
        $emulateVersion = Config::inst()->get(self::class, 'emulate_version');
        return match ($mode) {
            self::MODE_UNKNOWN => $this->modeUnknown(),
            self::MODE_NO_VIRUS, self::MODE_HAS_VIRUS => $emulateVersion,
            self::MODE_OFFLINE => $this->modeOffline(),
            default => $this->modeInvalid(),
        };
    }

    /**
     * {@inheritDoc}
     */
    protected function fileScan($filepath)
    {
        $mode = Config::inst()->get(self::class, 'mode');
        return match ($mode) {
            self::MODE_UNKNOWN => $this->modeUnknown(),
            self::MODE_NO_VIRUS => [
                'file'  => $filepath,
                'stats' => 'OK',
            ],
            self::MODE_HAS_VIRUS => [
                'file'  => $filepath,
                'stats' => 'Eicar-Test-Signature FOUND',
            ],
            self::MODE_OFFLINE => $this->modeOffline(),
            default => $this->modeInvalid(),
        };
    }

    protected function modeUnknown(): never
    {
        throw new LogicException('Must configure ' . self::class . '::mode config');
    }

    protected function modeOffline()
    {
        $this->last_exception = new ClamdSocketException(
            '*EMULATE MODE* No such file or directory "/not-real-root-folder/run/clamav/clamd.ctl"',
            2
        );

        return self::OFFLINE;
    }

    protected function modeInvalid(): never
    {
        throw new LogicException(
            'Invalid "mode" config with value "' . Config::inst()->get(self::class, 'mode')
            . '". Use constants provided in ' . self::class . ' class.'
        );
    }
}
