<?php namespace Constant\Replicon\Gen3;

use Constant\Service\BaseSoapService;
use Psr\Log\LoggerInterface;

class Timesheet extends BaseSoapService
{

    private $id;

    const ACTIVITY_NONE = 0;
    const ACTIVITY_TRAINING = 4;
    const ACTIVITY_VACATION = 6;
    const ACTIVITY_SICK = 7;
    const ACTIVITY_HOLIDAY = 8;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    protected $_timeRows = [];

    /**
     * Initialize class
     * @param LoggerInterface $log
     * @param $username
     * @param $password
     * @param array $options
     */
    public function __construct(LoggerInterface $log, $username, $password, $options = [])
    {
        $this->output = $log;
        $companyKey = $options['companyKey'];
        $hostname = 'na8.replicon.com';
        if (isset($options['hostname'])) {
            $hostname = $options['hostname'];
        }
        $this->_baseUri = "https://{$hostname}/{$companyKey}/services/";
        $this->username = $username;
        $this->password = $password;
        // $this->_debug = true;
        // $this->_options['proxy_host'] = '127.0.0.1';
        // $this->_options['proxy_port'] = '8888';
        $this->_options['login'] = "{$companyKey}\\{$this->username}";
        $this->_options['password'] = $this->password;

        $this->_options['trace'] = 1;
        $this->_options['exceptions'] = true;
        $this->_options['cache_wsdl'] = WSDL_CACHE_NONE;

        $this->_options['stream_context'] = stream_context_create([
            'http' => [
                'protocol_version' => '1.0'
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $this->_timeRows = [];
    }

    public function saveTimesheet()
    {
        $data = [
            'PutStandardTimesheet2' => [
                'timesheet' => [
                    'target' => [
                        'uri' => "{$this->id}",
                        'user' => null,
                        'date' => null
                    ],
                    'customFields' => [],
                    'rows' => $this->_timeRows,
                    'noticeExplicitlyAccepted' => false,
                    'bankedTime' => null
                ]
            ]
        ];

        $this->output->debug(print_r($data, true));

        $this->uri = $this->_baseUri . 'TimesheetService1.svc';
        $this->wsdl = $this->uri . '?singleWsdl';
        $this->_options['location'] = $this->uri . '/soap';
        $response = $this->processRequest('PutStandardTimesheet2', $data);
        $this->_handleError($data, $response);
        $this->recalculate();
        return $response;
    }

    public function recalculate()
    {
        $data = [
            'RecalculateScriptData' => [
                'timesheet' => [
                    'uri' => "{$this->id}",
                    'user' => null,
                    'date' => null
                ]
            ]
        ];

        $this->output->debug(print_r($data, true));

        $this->uri = $this->_baseUri . 'TimesheetService1.svc';
        $this->wsdl = $this->uri . '?singleWsdl';
        $this->_options['location'] = $this->uri . '/soap';
        $response = $this->processRequest('RecalculateScriptData', $data);
        $this->_handleError($data, $response);
    }

    public function createCell($date, $duration, $comment)
    {
        $hours = $duration;
        $minutes = 0;
        if (floor($duration) != $duration) {
            $hours = floor($duration);
            $minutes = ($duration - $hours) * 60;
        }
        $date = explode('-', $date);
        return [
            'date' => [
                'year' => $date[0],
                'month' => $date[1],
                'day' => $date[2]
            ],
            'duration' => [
                'hours' => $hours,
                'minutes' => $minutes,
                'seconds' => '0'
            ],
            'comments' => $comment,
            'customFieldValues' => []
        ];
    }

    public function addTimeRow($cells = [], $project = '', $task ='', $billable = false)
    {
        if (empty($project) || empty($task)) {
            throw new \Exception("Project and Task are required.  Maybe you meant to use Gen2 class?");
        }
        $billingRate = null;
        if ($billable) {
            $billingRate = [
                'displayText' => 'Project Rate',
                'name' => 'Project Rate',
                'uri' => 'urn:replicon:project-specific-billing-rate'
            ];
        }
        $this->_timeRows[] = [
            'target' => null,
            'project' => $project,
            'task' => $task,
            'billingRate' => $billingRate,
            'activity' => null,
            'customFieldValues' => [],
            'cells' => $cells
        ];
    }

    public function createProject($taskCode) {

        $projecturn = $this->_getProjectUrn($taskCode);
        return [
            'uri' => $projecturn,
            'name' => null,
            'parameterCorrelationId' => null
        ];
    }

    public function createTask($taskCode)
    {
        $taskurn = $this->_getTaskUrn($taskCode);
        return [
            'uri' => $taskurn,
            'name' => null,
            'parent' => null,
            'parameterCorrelationId' => null
        ];
    }

    protected $_projects = [];
    protected $_tasks = [];

    public function getTask($taskCode)
    {
        return $this->_getTaskUrn($taskCode);
    }

    public function getProject($taskCode)
    {
        return $this->_getProjectUrn($taskCode);
    }

    protected function _getProjectUrn($taskCode)
    {
        if (empty($this->_projects)) {
            $data = [
                'GetPageOfProjectsAvailableForTimeAllocationFilteredByClientAndTextSearch' => [
                    'page' => '1',
                    'pageSize' => '10000',
                    'timesheetUri' => $this->id,
                    'clientUri' => null,
                    'textSearch' => null,
                    'clientNullFilterBehaviorUri' => null
                ]
            ];

            $this->output->debug(print_r($data, true));

            $this->uri = $this->_baseUri . 'TimesheetService1.svc';
            $this->wsdl = $this->uri . '?singleWsdl';
            $this->_options['location'] = $this->uri . '/soap';
            $response = $this->processRequest('GetPageOfProjectsAvailableForTimeAllocationFilteredByClientAndTextSearch', $data);
            $this->_handleError($data, $response);
            foreach ($response->GetPageOfProjectsAvailableForTimeAllocationFilteredByClientAndTextSearchResult->TimeAllocationAvailableProjectDetails1 as $project) {
                $tasks = $this->_getTasks($project->project->uri);
                foreach ($tasks as $code => $taskuri) {
                    $this->_projects[$code] = $project->project->uri;
                }
            }
        }
        if (isset($this->_projects[$taskCode])) {
            return $this->_projects[$taskCode];
        }

        throw new \Exception("Could not find project for {$taskCode}");
    }

    protected function _getTasks($projectUrn)
    {
        if (isset($this->_tasks[$projectUrn])) {
            return $this->_tasks[$projectUrn];
        }

        $data = [
            'GetPageOfTasksAvailableForTimeAllocationFilteredByProjectAndTextSearch' => [
                'page' => '1',
                'pageSize' => '1000',
                'timesheetUri' => $this->id,
                'projectUri' => $projectUrn,
                'textSearch' => null
            ]
        ];
        $this->output->debug(print_r($data, true));

        $this->uri = $this->_baseUri . 'TimesheetService1.svc';
        $this->wsdl = $this->uri . '?singleWsdl';
        $this->_options['location'] = $this->uri . '/soap';
        $response = $this->processRequest('GetPageOfTasksAvailableForTimeAllocationFilteredByProjectAndTextSearch', $data);
        $this->_handleError($data, $response);
        $tasks = [];
        if (property_exists($response->GetPageOfTasksAvailableForTimeAllocationFilteredByProjectAndTextSearchResult, 'TimeAllocationAvailableTaskDetails1')) {
            $tasks = $response->GetPageOfTasksAvailableForTimeAllocationFilteredByProjectAndTextSearchResult->TimeAllocationAvailableTaskDetails1;
        }
        if (!is_array($tasks)) {
            $tasks = [$tasks];
        }
        foreach ($tasks as $task) {
            $taskCode = explode(' - ', $task->task->task->displayText);
            $this->_tasks[$projectUrn][trim($taskCode[count($taskCode) - 1])] = $task->task->task->uri;
        }        
        if (isset($this->_tasks[$projectUrn])) {
            return $this->_tasks[$projectUrn];
        }
        
        return []; // no tasks found for project
    }

    protected function _getTaskUrn($taskCode)
    {
        $projecturi = $this->_getProjectUrn($taskCode);
        if (!isset($this->_tasks[$projecturi])) {
            return '';
        }
        if (!isset($this->_tasks[$projecturi][$taskCode])) {
            return '';
        }
        return $this->_tasks[$projecturi][$taskCode];
    }

    public function createActivity($type = ACTIVITY_NONE)
    {
        throw new \Exception("Activities have not been implemented yet");
    }

    public function isGen2()
    {
        return false;
    }

    public function isGen3()
    {
        return true;
    }

}
