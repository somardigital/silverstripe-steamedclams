<?php

namespace Symbiote\SteamedClams\Jobs;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\SteamedClams\ClamAV;
use Symbiote\SteamedClams\Tasks\ClamAVScanTask;
use Symbiote\QueuedJobs\Services\QueuedJobService;

// Only declare the job when queuedjobs is installed. Declared at the top level (rather than
// inside an `if` block) so Silverstripe's class manifest can find it.
if (!class_exists(AbstractQueuedJob::class)) {
    return;
}

class ClamAVScanJob extends AbstractQueuedJob
{
    /**
     * Disable queueing on dev/build
     * @var bool
     */
    private static $disable_queue_on_devbuild = false;

    /**
     * Repeat at daily by default (in seconds).
     * @var int
     */
    private static $repeat_time = 86400;

    /**
     * Repeat at 2am by default
     * @var string
     */
    private static $time = '02:00:00';

    /**
     * NOTE: Called from ClamAVScan::requireDefaultRecords (2016-12-01)
     */
    public function requireDefaultRecords()
    {
        if (Config::inst()->get(self::class, 'disable_queue_on_devbuild')) {
            return;
        }
        $jobDescriptorID = $this->queueMyselfIfNeeded();
        if ($jobDescriptorID !== null) {
            DB::alteration_message('Queued ClamAVScanJob #' . $jobDescriptorID, 'created');
        }
    }

    public function setup()
    {
        parent::setup();

        // Recommended for long running jobs that don't increment 'currentStep'
        // https://github.com/symbiote/silverstripe-queuedjobs
        $this->currentStep = -1;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'ClamAV Virus Scan Task - Scan missed files';
    }

    /**
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function process()
    {
        $task = Injector::inst()->get(ClamAVScanTask::class);
        $input = new ArrayInput([]);
        $input->setInteractive(false);
        $task->run($input, PolyOutput::create(PolyOutput::FORMAT_ANSI));

        $this->currentStep = 1;
        $this->isComplete = true;
    }

    public function afterComplete()
    {
        $this->queueMyselfIfNeeded();
    }

    /**
     * Add this job if there are files to scan for viruses.
     *
     * @var int|null
     * @return null
     */
    public function queueMyselfIfNeeded()
    {
        // NOTE(Jake): Perhaps add '$cache' flag here to stop
        // thrashing in ClamAVScan::onAfterWrite()
        $clamAV = Injector::inst()->get(ClamAV::class);
        $list = $clamAV->getFailedToScanFileList();
        if (!$list || $list->count() == 0) {
            return null;
        }

        return $this->queueMyself();
    }

    /**
     * Add this job to the queue at the desired times
     *
     * @var int|null
     * @return null
     */
    public function queueMyself()
    {
        $repeat_time = Config::inst()->get(self::class, 'repeat_time');
        if (!$repeat_time) {
            return null;
        }
        $time = Config::inst()->get(self::class, 'time');
        if (!$time) {
            return null;
        }

        $class = self::class;
        $nextJob = new $class();
        $job = Injector::inst()->get(QueuedJobService::class);
        $jobDescriptorID = $job->queueJob($nextJob, date('Y-m-d', time() + $repeat_time) . ' ' . $time);

        return $jobDescriptorID;
    }
}
