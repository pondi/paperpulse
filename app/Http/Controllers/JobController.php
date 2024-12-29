<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;

class JobController extends Controller
{
    protected $jobs;
    protected $supervisors;
    protected $waitTime;

    public function __construct(
        JobRepository $jobs,
        MasterSupervisorRepository $supervisors,
        WaitTimeCalculator $waitTime
    ) {
        $this->jobs = $jobs;
        $this->supervisors = $supervisors;
        $this->waitTime = $waitTime;
    }

    protected function cleanString($str) {
        // Fjern ugyldige UTF-8-tegn
        $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        // Erstatt alle ikke-utskrivbare tegn med mellomrom
        $str = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $str);
        return trim($str);
    }

    public function index()
    {
        try {
            // Hent jobber med forskjellige statuser
            $failedJobs = collect($this->jobs->getFailed())->take(50);
            $completedJobs = collect($this->jobs->getCompleted())->take(50);
            $pendingJobs = collect($this->jobs->getPending())->take(50);

            // Mapper alle jobbene til samme format
            $allJobs = collect()
                ->concat($pendingJobs->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'name' => $this->cleanString($job->name),
                        'status' => 'processing',
                        'queue' => $this->cleanString($job->queue),
                        'started_at' => $job->reserved_at ?? $job->pushed_at,
                        'progress' => $job->progress ?? 0,
                    ];
                }))
                ->concat($completedJobs->map(function ($job) {
                    return [
                        'id' => $job->id,
                        'name' => $this->cleanString($job->name),
                        'status' => 'completed',
                        'queue' => $this->cleanString($job->queue),
                        'started_at' => $job->completed_at,
                        'progress' => 100,
                    ];
                }))
                ->concat($failedJobs->map(function ($job) {
                    $exception = property_exists($job, 'exception') ? $job->exception : 'Ukjent feil';
                    return [
                        'id' => $job->id,
                        'name' => $this->cleanString($job->name),
                        'status' => 'failed',
                        'queue' => $this->cleanString($job->queue),
                        'started_at' => $job->failed_at,
                        'progress' => 0,
                        'exception' => $this->cleanString($exception),
                    ];
                }));

            // Sorter jobbene etter starttidspunkt og begrens til de 50 siste
            $sortedJobs = $allJobs->sortByDesc('started_at')->values();

            return response()->json([
                'data' => $sortedJobs,
                'success' => true,
                'counts' => [
                    'pending' => $pendingJobs->count(),
                    'completed' => $completedJobs->count(),
                    'failed' => $failedJobs->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Kunne ikke hente jobbstatus: ' . $this->cleanString($e->getMessage()),
                'error' => $this->cleanString($e->getMessage()),
                'success' => false
            ], 500);
        }
    }
} 