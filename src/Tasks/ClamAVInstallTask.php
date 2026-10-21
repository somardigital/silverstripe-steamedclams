<?php

namespace Symbiote\SteamedClams\Tasks;

use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Exception;

class ClamAVInstallTask extends ClamAVBaseTask
{
    /**
     * @var string
     */
    protected string $title = 'ClamAV Virus Install Task';

    /**
     * @var string
     */
    protected static string $description = 'Scans all files that haven\'t been scanned yet and aren\'t queued for later scanning.';

    /**
     * Limit the `File` lists for testing purposes
     */
    protected $debug_limit = 0;

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $result = parent::execute($input, $output);
        if ($result !== Command::SUCCESS) {
            return $result;
        }

        $this->log('Starting ClamAV install task...');

        $list = $this->clamAV->getInitialFileToScanList();
        $listCount = $list->count();
        if ($listCount > 0) {
            $this->log('------------------------------------');
            $this->log('Scanning the ' . $listCount . ' files that were uploaded before module installation');
            $this->log('------------------------------------');
            $this->scanListChunked($list);
            $this->log('Finished ClamAV task.');
        } else {
            $this->log('Finished ClamAV task. No action was required.');
        }
        return Command::SUCCESS;
    }
}
