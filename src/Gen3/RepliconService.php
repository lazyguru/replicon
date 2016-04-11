<?php namespace Constant\Replicon\Gen3;

use Constant\Service\BaseSoapService;
use Psr\Log\LoggerInterface;

class RepliconService extends BaseSoapService
{
    protected $_baseUri = '';

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

    }

    public function getTimesheetById($timesheetid)
    {
        $data = [
            "GetStandardTimesheet2" => [
                "timesheetUri" => $timesheetid,
            ]
        ];

        $this->output->debug(print_r($data, true));

        $this->uri = $this->_baseUri . 'TimesheetService1.svc';
        $this->wsdl = $this->uri . '?singleWsdl';
        $this->_options['location'] = $this->uri . '/soap';
        $response = $this->processRequest('GetStandardTimesheet2', $data);
        $this->_handleError($data, $response);
        return $response->GetStandardTimesheet2Result;
    }

    public function getTimesheetByUseridDate($userid, $date = '')
    {
        if (empty($date)) {
            $date = date('Y-m-d', time() - 86400);
        }
        $date = explode('-', $date);

        $data = [
            "GetTimesheetForDate2" => [
                "userUri" => $userid,
                "date" => [
                    "year" => $date[0],
                    "month" => $date[1],
                    "day" => $date[2]
                ],
                "timesheetGetOptionUri" => null
            ]
        ];

        $this->output->debug(print_r($data, true));

        $this->uri = $this->_baseUri . 'TimesheetService1.svc';
        $this->wsdl = $this->uri . '?singleWsdl';
        $this->_options['location'] = $this->uri . '/soap';
        $response = $this->processRequest('GetTimesheetForDate2', $data);
        $this->_handleError($data, $response);
        return $response->GetTimesheetForDate2Result->timesheet->uri;
    }

    public function getTaskByCode($code)
    {
        $data = array(
            'Action' => 'Query',
            'QueryType' => 'TaskByCode',
            'DomainType' => 'Replicon.Project.Domain.Task',
            'Args' => array(
                $code
            )
        );
        $this->output->debug(print_r($data, true));
        $data = json_encode($data);

        $response = $this->processRequest($data);
        $this->_handleError($data, $response);
        return $response->Value[0]->Properties;
    }

    public function findUseridByLogin($username)
    {

        $data = [
            'GetUser2' => [
                'user' => [
                    'uri' => null,
                    'loginName' => $username,
                    'parameterCorrelationId' => null
                ]
            ]
        ];
        $this->output->debug(print_r($data, true));

        $this->uri = $this->_baseUri . 'UserService1.svc';
        $this->wsdl = $this->uri . '?singleWsdl';
        $this->_options['location'] = $this->uri . '/soap';
        $response = $this->processRequest('GetUser2', $data);
        $this->_handleError($data, $response);
        $user = new \stdClass;
        $user->Id = $response->GetUser2Result->uri;
        return $user;
    }

    public function addTimeEntry($timesheet, $date, $code, $duration, $comment)
    {
        $date = explode('-', $date);
        $data = [
            "Action" => "Edit",
            "Type" => "Replicon.Suite.Domain.EntryTimesheet",
            "Identity" => (string)$timesheet,
            "Operations" => [
                [
                    "__operation" => "CollectionAdd",
                    "Collection" => "TimeEntries",
                    "Operations" => [
                        [
                            "__operation" => "SetProperties",
                            "CalculationModeObject" => [
                                "Type" => "Replicon.TimeSheet.Domain.CalculationModeObject",
                                "Identity" => "CalculateInOutTime",
                                "Properties" => [
                                    "Name" => "CalculationModeObject_CalculateInOutTime"
                                ]
                            ],
                            "EntryDate" => [
                                "__type" => "Date",
                                "Year" => $date[0],
                                "Month" => $date[1],
                                "Day" => $date[2]
                            ],
                            "Duration" => [
                                "__type" => "Timespan",
                                "Hours" => $duration
                            ],
                            "Comments" => $comment,
                            "Task" => [
                                "Identity" => (string)$code
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->output->debug(print_r($data, true));
        $data = json_encode($data);

        $response = $this->processRequest($data);
        $this->_handleError($data, $response);
    }

}
