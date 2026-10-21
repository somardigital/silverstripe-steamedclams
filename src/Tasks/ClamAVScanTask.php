<?php

namespace Symbiote\SteamedClams\Tasks;

use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Exception;

class ClamAVScanTask extends ClamAVBaseTask
{
    /**
     * @var string
     */
    protected string $title = 'ClamAV Virus Scan Task';

    /**
     * @var string
     */
    protected static string $description = 'Scans files missed due to ClamAV daemon being unavailable at time of file upload.';

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

        $this->log('Starting ClamAV task...');

        $list = $this->clamAV->getFailedToScanFileList();
        $listCount = $list->count();
        if ($listCount > 0) {
            $this->log('------------------------------------');
            $this->log('Scanning the ' . $listCount . ' files that couldn\'t be scanned due 
            to previous ClamAV daemon connectivity issues.');
            $this->log('------------------------------------');
            $this->scanListChunked($list, $this->debug_limit);
            $this->log('Finished ClamAV task.');
        } else {
            $this->log('Finished ClamAV task. No action was required.');
        }
        return Command::SUCCESS;
    }
}
