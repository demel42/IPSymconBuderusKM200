<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class BuderusKM200 extends IPSModule
{
    use BuderusKM200\StubsCommonLib;
    use BuderusKM200LocalLib;

    private static $semaphoreTM = 30 * 1000;

    private $SemaphoreID;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonContruct(__DIR__);
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('host', '');
        $this->RegisterPropertyInteger('port', 80);

        $this->RegisterPropertyString('gateway_password', '');
        $this->RegisterPropertyString('private_password', '');

        $fields = [
            [
                'datapoint'  => '/system/healthStatus',
                'vartype'    => VARIABLETYPE_INTEGER,
            ],
        ];
        $this->RegisterPropertyString('fields', json_encode($fields));

        $this->RegisterPropertyInteger('convert_script', 0);

        $this->RegisterPropertyInteger('update_interval', '60');

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('UpdateData', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateData", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $host = $this->ReadPropertyString('host');
        if ($host == '') {
            $this->SendDebug(__FUNCTION__, '"host" is needed', 0);
            $r[] = $this->Translate('Hostname must be specified');
        }

        $port = $this->ReadPropertyInteger('port');
        if ($port == 0) {
            $this->SendDebug(__FUNCTION__, '"port" is needed', 0);
            $r[] = $this->Translate('Port must be specified');
        }

        $gateway_password = $this->ReadPropertyString('gateway_password');
        $private_password = $this->ReadPropertyString('private_password');
        if ($gateway_password == '' || $private_password == '') {
            $this->SendDebug(__FUNCTION__, '"gateway_password" / "private_password" is needed', 0);
            $r[] = $this->Translate('Passwords must be specified');
        }

        return $r;
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        if ($this->version2num($oldInfo) < $this->version2num('1.24')) {
            $r[] = $this->Translate('Set ident of media objects');
        }

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = '';

        if ($this->version2num($oldInfo) < $this->version2num('1.24')) {
            $filename = 'media' . DIRECTORY_SEPARATOR . $this->InstanceID . '.csv';
            @$mediaID = IPS_GetMediaIDByFile($filename);
            if ($mediaID != false) {
                IPS_SetIdent($mediaID, 'Datapoints');
            }
        }

        return $r;
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function findVariables($objID, &$objList)
    {
        $chldIDs = IPS_GetChildrenIDs($objID);
        foreach ($chldIDs as $chldID) {
            $obj = IPS_GetObject($chldID);
            switch ($obj['ObjectType']) {
                case OBJECTTYPE_VARIABLE:
                    if (substr($obj['ObjectIdent'], 0, 3) == 'DP_') {
                        $objList[] = $obj;
                    }
                    break;
                case OBJECTTYPE_CATEGORY:
                    $this->findVariables($chldID, $objList);
                    break;
                default:
                    break;
            }
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = ['convert_script'];
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $fields = json_decode($this->ReadPropertyString('fields'), true);

        $vpos = 1000;

        $varList = [];
        foreach ($fields as $field) {
            $this->SendDebug(__FUNCTION__, 'field=' . print_r($field, true), 0);
            $datapoint = $this->GetArrayElem($field, 'datapoint', '');
            $vartype = $this->GetArrayElem($field, 'vartype', -1);

            if ($datapoint == '' || $vartype == -1) {
                $this->SendDebug(__FUNCTION__, 'ignore field: datapoint=' . $datapoint . ', vartype=' . $vartype, 0);
                continue;
            }

            $ident = 'DP' . str_replace('/', '_', $datapoint);

            $this->SendDebug(__FUNCTION__, 'register variable: ident=' . $ident . ', vartype=' . $vartype, 0);

            $this->MaintainVariable($ident, $datapoint, $vartype, '', $vpos++, true);
            $varList[] = $ident;
        }

        $this->SendDebug(__FUNCTION__, 'varList=' . print_r($varList, true), 0);

        $objList = [];
        $this->findVariables($this->InstanceID, $objList);
        foreach ($objList as $obj) {
            $ident = $obj['ObjectIdent'];
            if (!in_array($ident, $varList)) {
                $this->SendDebug(__FUNCTION__, 'unregister variable: ident=' . $ident, 0);
                $this->UnregisterVariable($ident);
            }
        }

        $this->MaintainVariable('Notifications', $this->Translate('Notifications'), VARIABLETYPE_STRING, '~HTMLBox', $vpos++, true);
        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateData', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('update_interval');
        $msec = $min > 0 ? $min * 60 * 1000 : 0;
        $this->MaintainTimer('UpdateData', $msec);
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Buderus KM200');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'host',
                    'caption' => 'Host'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'port',
                    'caption' => 'Port'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'gateway_password',
                    'caption' => 'Gateway password'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'private_password',
                    'caption' => 'Private password'
                ],
            ],
            'caption' => 'Basic configuration',
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'     => 'List',
                    'name'     => 'fields',
                    'caption'  => 'Fields',
                    'rowCount' => 15,
                    'add'      => true,
                    'delete'   => true,
                    'columns'  => [
                        [
                            'caption' => 'Datapoint',
                            'name'    => 'datapoint',
                            'add'     => '',
                            'width'   => 'auto',
                            'edit'    => [
                                'type' => 'ValidationTextBox'
                            ]
                        ],
                        [
                            'caption' => 'Variable type',
                            'name'    => 'vartype',
                            'add'     => VARIABLETYPE_FLOAT,
                            'width'   => '150px',
                            'edit'    => [
                                'type'    => 'Select',
                                'options' => [
                                    [
                                        'caption' => 'Boolean',
                                        'value'   => VARIABLETYPE_BOOLEAN
                                    ],
                                    [
                                        'caption' => 'Integer',
                                        'value'   => VARIABLETYPE_INTEGER
                                    ],
                                    [
                                        'caption' => 'Float',
                                        'value'   => VARIABLETYPE_FLOAT
                                    ],
                                    [
                                        'caption' => 'String',
                                        'value'   => VARIABLETYPE_STRING
                                    ],
                                ]
                            ]
                        ],
                    ],
                ],
                [
                    'type'    => 'SelectScript',
                    'name'    => 'convert_script',
                    'caption' => 'convert values'
                ],
            ],
            'caption' => 'Datapoint configurations',
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'suffix'  => 'Minutes',
            'caption' => 'Update interval',
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Verify access',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "VerifyAccess", "");',
        ];
        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update data',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateData", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'Button',
                    'caption' => 'Datapoint-sheet',
                    'onClick' => $this->GetModulePrefix() . '_DatapointSheet($id);'
                ],
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'UpdateData':
                $this->UpdateData();
                break;
            case 'VerifyAccess':
                $this->VerifyAccess();
                break;
            case 'ShowRecordings':
                $this->ShowRecordings();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }

    private function UpdateData()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $now = time();

        $convert_script = $this->ReadPropertyInteger('convert_script');

        $fields = json_decode($this->ReadPropertyString('fields'), true);

        foreach ($fields as $field) {
            $datapoint = $this->GetArrayElem($field, 'datapoint', '');
            if ($datapoint == '/system/healthStatus') {
                $r = $this->GetData($datapoint);
                if ($r == false) {
                    $ident = 'DP' . str_replace('/', '_', $datapoint);
                    $this->SetValue($ident, 0); // Error
                    $this->SendDebug(__FUNCTION__, 'unable to get datapoint "' . $datapoint . '"', 0);
                    return;
                }
            }
        }

        foreach ($fields as $field) {
            $datapoint = $this->GetArrayElem($field, 'datapoint', '');
            $result = $this->GetData($datapoint);
            if ($result == false) {
                $this->SendDebug(__FUNCTION__, 'unable to get datapoint "' . $datapoint . '"', 0);
                return;
            }

            $vartype = $this->GetArrayElem($field, 'vartype', -1);
            $type = $this->GetArrayElem($result, 'type', '');
            $value = $this->GetArrayElem($result, 'value', '');

            $do_convert = false;
            if ($type == 'floatValue' && $vartype != VARIABLETYPE_FLOAT) {
                $do_convert = true;
            }
            if ($type == 'stringValue' && $vartype != VARIABLETYPE_STRING) {
                $do_convert = true;
            }

            $this->SendDebug(__FUNCTION__, 'datapoint=' . $datapoint . ', result=' . print_r($result, true), 0);

            if ($do_convert && IPS_ScriptExists($convert_script)) {
                $info = [
                    'InstanceID'    => $this->InstanceID,
                    'datapoint'     => $datapoint,
                    'vartype'       => $vartype,
                    'value'         => $value,
                    'result'        => json_encode($result),
                ];
                $r = IPS_RunScriptWaitEx($convert_script, $info);
                $this->SendDebug(__FUNCTION__, 'convert: datapoint=' . $datapoint . ', orgval=' . $value . ', value=' . ($r == false ? '<nop>' : $r), 0);
                if ($r != false) {
                    $value = $r;
                    $do_convert = false;
                }
            }
            if ($do_convert) {
                switch ($vartype) {
                    case VARIABLETYPE_BOOLEAN:
                        if (isset($result['allowedValues'])) {
                            $orgval = $value;
                            $allowedValues = $result['allowedValues'];
                            foreach ($allowedValues as $val => $txt) {
                                if ($txt == $value) {
                                    $value = (bool) $val;
                                    break;
                                }
                            }
                            $this->SendDebug(__FUNCTION__, 'string2bool: datapoint=' . $datapoint . ', orgval=' . $orgval . ', value=' . $this->bool2str($value) . ', allowedValues=' . print_r($allowedValues, true), 0);
                        }
                        break;
                    case VARIABLETYPE_INTEGER:
                        switch ($datapoint) {
                            case '/gateway/DateTime':
                            case '/heatSources/energyMonitoring/startDateTime':
                                $value = strtotime($value);
                                break;
                            default:
                                if (isset($result['allowedValues'])) {
                                    $orgval = $value;
                                    $allowedValues = $result['allowedValues'];
                                    foreach ($allowedValues as $val => $txt) {
                                        if ($txt == $value) {
                                            $value = $val;
                                            break;
                                        }
                                    }
                                    $this->SendDebug(__FUNCTION__, 'string2int: datapoint=' . $datapoint . ', orgval=' . $orgval . ', value=' . $value . ', allowedValues=' . print_r($allowedValues, true), 0);
                                }
                                break;
                        }
                        break;
                }
            }

            $ident = 'DP' . str_replace('/', '_', $datapoint);
            $this->SetValue($ident, $value);
        }

        $result = $this->GetData('/notifications');
        $this->SendDebug(__FUNCTION__, 'notifications=' . print_r($result, true), 0);
        if ($result == false) {
            $html = $this->Translate('Unable to get notifications');
        } else {
            $html = '<html>' . PHP_EOL;
            $html .= '<body>' . PHP_EOL;
            $html .= '<style>' . PHP_EOL;
            $html .= 'body { margin: 1; padding: 0; font-family: "Open Sans", sans-serif; font-size: 20px; }' . PHP_EOL;
            $html .= 'table { border-collapse: collapse; border: 0px solid; margin: 0.5em;}' . PHP_EOL;
            $html .= 'th, td { padding: 1; }' . PHP_EOL;
            $html .= 'thead, tdata { text-align: left; }' . PHP_EOL;
            $html .= '#spalte_zeitpunkt { width: 125px; }' . PHP_EOL;
            $html .= '#spalte_text { }' . PHP_EOL;
            $html .= '</style>' . PHP_EOL;
            if (count($result['values']) == 0) {
                $html .= $this->Translate('There are no notifications') . PHP_EOL;
            } else {
                $html .= '<table>' . PHP_EOL;
                $html .= '<colgroup><col id="spalte_zeitpunkt"></colgroup>' . PHP_EOL;
                $html .= '<colgroup><col id="spalte_text"></colgroup>' . PHP_EOL;
                $html .= '<thead>' . PHP_EOL;
                $html .= '<tr>' . PHP_EOL;
                $html .= '<th>Zeitpunkt</th>' . PHP_EOL;
                $html .= '<th>Meldung</th>' . PHP_EOL;
                $html .= '</tr>' . PHP_EOL;
                $html .= '</thead>' . PHP_EOL;
                $html .= '<tdata>' . PHP_EOL;
                foreach ($result['values'] as $item) {
                    $this->SendDebug(__FUNCTION__, ' ... notification=' . print_r($item, true), 0);
                    $ts = $this->GetArrayElem($item, 't', '');
                    $errorCode = $this->GetArrayElem($item, 'dcd', '?');
                    $addCode = $this->GetArrayElem($item, 'ccd', '?');
                    $classCode = $this->GetArrayElem($item, 'cat', '?');
                    $s = $errorCode . ' ' . $addCode . ' ' . $classCode;
                    $html .= '<tr>' . PHP_EOL;
                    $html .= '<td>' . $ts . '</td>' . PHP_EOL;
                    $html .= '<td>' . $s . '</td>' . PHP_EOL;
                    $html .= '</tr>' . PHP_EOL;
                }
            }
            $html .= '</tdata>' . PHP_EOL;
            $html .= '</table>' . PHP_EOL;
        }
        $html .= '</body>' . PHP_EOL;
        $html .= '</html>' . PHP_EOL;

        $this->SetValue('Notifications', $html);

        $this->SetValue('LastUpdate', $now);
        $this->MaintainStatus(IS_ACTIVE);

        $dur = time() - $now;
        $min = $this->ReadPropertyInteger('update_interval');
        $sec = $min * 60 - $dur;
        // minimal 50% Pause eines Update-Zyklus
        if ($sec < ($min * 60 / 2)) {
            $sec = $min * 60;
        }
        $this->MaintainTimer('UpdateData', $sec * 1000);
    }

    private function VerifyAccess()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            $msg = $this->GetStatusText();
            $this->PopupMessage($msg);
            return;
        }

        $msg = '';
        $r = $this->GetData('/system/healthStatus');
        if ($r == false) {
            $msg = $this->Translate('Access failed') . ':' . PHP_EOL;
        } else {
            $msg = $this->Translate('Access ok') . ':' . PHP_EOL;
            $msg .= '  ' . $this->Translate('System health') . ': ' . $r['value'] . PHP_EOL;
            $msg .= PHP_EOL;
            $r = $this->GetData('/system/brand');
            if ($r != false) {
                $msg .= '  ' . $this->Translate('Brand') . ': ' . $r['value'] . PHP_EOL;
            }
            $r = $this->GetData('/system/bus');
            if ($r != false) {
                $msg .= '  ' . $this->Translate('Bus') . ': ' . $r['value'] . PHP_EOL;
            }
            $r = $this->GetData('/system/systemType');
            if ($r != false) {
                $msg .= '  ' . $this->Translate('System type') . ': ' . $r['value'] . PHP_EOL;
            }
            $msg .= PHP_EOL;
            $r = $this->GetData('/gateway/versionHardware');
            if ($r != false) {
                $msg .= '  ' . $this->Translate('Hardware version') . ': ' . $r['value'] . PHP_EOL;
            }
            $r = $this->GetData('/gateway/versionFirmware');
            if ($r != false) {
                $msg .= '  ' . $this->Translate('Firmware version') . ': ' . $r['value'] . PHP_EOL;
            }
            $msg .= PHP_EOL;
            $r = $this->GetData('/gateway/DateTime');
            if ($r != false) {
                $ts = strtotime($r['value']);
                $msg .= '  ' . $this->Translate('System time') . ': ' . date('d.m.Y H:i:s', $ts) . PHP_EOL;
            }
        }
        $this->PopupMessage($msg);
    }

    private function ShowRecordings()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $serviceList = [];
        $fields = json_decode($this->ReadPropertyString('fields'), true);
        foreach ($fields as $field) {
            $datapoint = $this->GetArrayElem($field, 'datapoint', '');

            if (strncmp($datapoint, '/heatSources/hs1/', strlen('/heatSources/hs1/')) == 0) {
                if (in_array('/heatSources/hs1/actualPower', $serviceList) == false) {
                    $serviceList[] = '/heatSources/hs1/actualPower';
                }
            }
            if (strncmp($datapoint, '/solarCircuits/sc1/', strlen('/solarCircuits/sc1/')) == 0) {
                if (in_array('/solarCircuits/sc1/solarYield', $serviceList) == false) {
                    $serviceList[] = '/solarCircuits/sc1/solarYield';
                }
            }
        }
        $this->SendDebug(__FUNCTION__, 'serviceList=' . print_r($serviceList, true), 0);

        $now = time();
        $intervals = [
            date('Y-m-d', $now),
            date('Y-m-d', $now - (24 * 60 * 60)),
            date('Y-m', $now),
            date('Y', $now),
        ];
        foreach ($intervals as $interval) {
            foreach ($serviceList as $service) {
                $jdata = $this->GetData($service);
                if ((isset($jdata['recordable']) && $jdata['recordable']) == false) {
                    $this->SendDebug(__FUNCTION__, 'service=' . $service . ' ist not recordable', 0);
                    continue;
                }

                $unit = isset($jdata['unitOfMeasure']) ? $jdata['unitOfMeasure'] : '';

                $jdata = $this->GetData('/recordings' . $service . '?interval=' . $interval);
                $this->SendDebug(__FUNCTION__, 'service=' . $service . ', jdata=' . print_r($jdata, true), 0);

                $interval = $jdata['interval'];
                $sampleRate = $jdata['sampleRate'];
                switch ($sampleRate) {
                    case 'P1H':
                        $timeGrid = 'day';
                        $scale = 1;
                        break;
                    case 'P1D':
                        $timeGrid = 'month';
                        $scale = 24;
                        break;
                    case 'P31D':
                        $timeGrid = 'year';
                        $scale = 24 * 30;
                        break;
                    default:
                        $timeGrid = $sampleRate;
                        break;
                }
                $recordingType = $jdata['recording-type'];
                $recording = $jdata['recording'];
                $v = [];
                for ($i = 0; $i < count($recording); $i++) {
                    $y = $recording[$i]['y'];
                    $c = $recording[$i]['c'];
                    $factor = $c > 0 ? ($c / $scale) : 1;
                    if ($c > 0) {
                        switch ($unit) {
                            case 'kW':
                                $y /= ($c / $scale);
                                $_unit = 'kWh';
                                break;
                            case 'Wh':
                                $y /= ($c / $scale);
                                $y /= 1000.0;
                                $_unit = 'kWh';
                                break;
                            default:
                                $_unit = $unit;
                                break;
                        }
                    } else {
                        $y = 0;
                    }
                    $v[] = sprintf('%02d=%.2f', $i + 1, $y);
                }
                $this->SendDebug(__FUNCTION__, 'interval=' . $interval . ', grid=' . $timeGrid . ' => ' . implode(', ', $v) . ' ' . $_unit, 0);
            }
        }
    }

    private function traverseService($service, &$entList)
    {
        $skipService = [
            '/gateway/firmware',
            '/gateway/userpassword',
            '/gateway/boschSHPassword',
            '/gateway/portalPassword',
            '/gateway/knxPassword',
            '/gateway/haiPassword',
            '/gateway/instPassword',
            '/gateway/version',
            '/gateway/update/version',
            '/gateway/update/request',
            '/gateway/update/strategy',
            '/gateway/logging/switch',
            '/gateway/logging/reqBusVar',
            '/system/busReq',
            '/system/holidayModes/hm1/delete',
            '/system/holidayModes/hm2/delete',
            '/system/holidayModes/hm3/delete',
            '/system/holidayModes/hm4/delete',
            '/system/holidayModes/hm5/delete',
            '/heatingCircuits/hc1/timeToNextSetpoint',
            '/heatingCircuits/hc1/nextSetpoint',
            '/heatingCircuits/hc1/designTemp',
            '/heatingCircuits/hc1/roomTempOffset',
            '/heatingCircuits/hc1/heatCurveMax',
            '/heatingCircuits/hc1/controlType',
            '/heatingCircuits/hc1/solarInfluence',
            '/heatingCircuits/hc1/roomInfluence',
            '/dhwCircuits/dhw1/tdsetPoint',
            '/dhwCircuits/dhw1/tdweekDay',
            '/dhwCircuits/dhw1/tddayTime',
            '/dhwCircuits/dhw1/cpStartph',
            '/dhwCircuits/dhw1/cpoperationMode',
        ];
        if (in_array($service, $skipService)) {
            $this->SendDebug(__FUNCTION__, 'skip unreadable service=' . $service, 0);
            return;
        }

        $jdata = $this->GetData($service);
        $this->SendDebug(__FUNCTION__, 'service=' . $service . ', jdata=' . print_r($jdata, true), 0);
        if ($jdata == false) {
            return;
        }

        $ent = [];

        switch ($jdata['type']) {
            case 'refEnum':
                foreach ($jdata['references'] as $subService) {
                    $this->traverseService($subService['id'], $entList);
                }
                break;
            case 'moduleList':
                foreach ($jdata['values'] as $Modules) {
                    echo $jdata['type'] . '=' . print_r($Modules, true) . PHP_EOL;
                }
                break;
            case 'floatValue':
                $ent['type'] = $jdata['type'];
                $ent['value'] = $jdata['value'];
                $ent['unit'] = $jdata['unitOfMeasure'];
                //echo $jdata['type'] . ' ' . print_r($jdata, true) . PHP_EOL;
                if (isset($jdata['minValue']) && isset($jdata['maxValue'])) {
                    $ent['range'] = $jdata['minValue'] . ' ... ' . $jdata['maxValue'];
                } elseif (isset($jdata['minValue'])) {
                    $ent['range'] = $jdata['minValue'] . ' ... ';
                } elseif (isset($jdata['maxValue'])) {
                    $ent['range'] = ' ... ' . $jdata['maxValue'];
                }
                if (isset($jdata['state'])) {
                    $r = [];
                    foreach ($jdata['state'] as $s) {
                        foreach ($s as $var => $val) {
                            $r[] = $var . '=' . $val;
                        }
                    }
                    if ($r != []) {
                        $ent['range'] = implode(', ', $r);
                    }
                }
                break;
            case 'stringValue':
                $ent['type'] = $jdata['type'];
                $ent['value'] = $jdata['value'];
                if (isset($jdata['allowedValues'])) {
                    $ent['range'] = implode(', ', $jdata['allowedValues']);
                }
                break;
            case 'errorList':
                break;
            case 'switchProgram':
            case 'systeminfo':
            case 'arrayData':
            case 'yRecording':
                $ent['type'] = $jdata['type'];
                $ent['value'] = ' --- ' . $this->Translate('not useable') . ' --- ';
                break;
            default:
                break;
        }
        if ($ent != []) {
            $ent['name'] = $service;
            $ent['writeable'] = isset($jdata['writeable']) && $jdata['writeable'] ? true : false;
            $ent['recordable'] = isset($jdata['recordable']) && $jdata['recordable'] ? true : false;
            $entList[] = $ent;
        }
    }

    public function DatapointSheet()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            $msg = $this->GetStatusText();
            $this->PopupMessage($msg);
            return;
        }

        $urls = ['/gateway', '/system', '/heatSources', '/heatingCircuits', '/solarCircuits', '/dhwCircuits'];

        $this->SendDebug(__FUNCTION__, 'urls=' . print_r($urls, true), 0);

        $entList = [];
        foreach ($urls as $url) {
            $this->traverseService($url, $entList);
        }
        $this->SendDebug(__FUNCTION__, 'entList=' . print_r($entList, true), 0);

        $title = [];
        $title[] = $this->Translate('Name');
        $title[] = $this->Translate('Type');
        $title[] = $this->Translate('Unit');
        $title[] = $this->Translate('writeable?');
        $title[] = $this->Translate('recordable?');
        $title[] = $this->Translate('current value');
        $title[] = $this->Translate('possible values');
        $buf = implode(';', $title) . PHP_EOL;

        foreach ($entList as $ent) {
            $row = [];
            $row[] = '"' . $ent['name'] . '"';
            $row[] = $ent['type'];
            $row[] = isset($ent['unit']) ? '"' . $ent['unit'] . '"' : '';
            $row[] = isset($ent['writeable']) && $ent['writeable'] ? 'Ja' : 'Nein';
            $row[] = isset($ent['recordable']) && $ent['recordable'] ? 'Ja' : 'Nein';
            $row[] = isset($ent['value']) ? '"' . $ent['value'] . '"' : '';
            $row[] = isset($ent['range']) ? '"' . $ent['range'] . '"' : '';
            $buf .= implode(';', $row) . PHP_EOL;
        }

        $this->MaintainMedia('Datapoints', $this->Translate('Buderus KM200 Datapoints'), MEDIATYPE_DOCUMENT, '.csv', false, 1000, true);
        $this->SetMediaContent('Datapoints', base64_encode($buf)););
    }

    private function GetKey()
    {
        $gateway_password = $this->ReadPropertyString('gateway_password');
        $private_password = $this->ReadPropertyString('private_password');

        // Achtung: Gerätepasswort ohne Bindestriche
        $gateway_password = preg_replace('/-/', '', $gateway_password);

        // Salt der MD5-Hashes zur AES-Schlüsselerzeugung
        $crypt_md5_salt = pack(
            'c*',
            0x86,
            0x78,
            0x45,
            0xe9,
            0x7c,
            0x4e,
            0x29,
            0xdc,
            0xe5,
            0x22,
            0xb9,
            0xa7,
            0xd3,
            0xa3,
            0xe0,
            0x7b,
            0x15,
            0x2b,
            0xff,
            0xad,
            0xdd,
            0xbe,
            0xd7,
            0xf5,
            0xff,
            0xd8,
            0x42,
            0xe9,
            0x89,
            0x5a,
            0xd1,
            0xe4
        );

        // Erste Hälfte des Schlüssels: MD5 von ( Gerätepasswort . Salt )
        $key_1 = md5($gateway_password . $crypt_md5_salt, true);
        // Zweite Hälfte des Schlüssels - initial: MD5 von ( Salt )
        $key_2_initial = md5($crypt_md5_salt, true);
        // Zweite Hälfte des Schlüssels - privat: MD5 von ( Salt . privates Passwort )
        $key_2_private = md5($crypt_md5_salt . $private_password, true);

        $crypt_key_private = bin2hex($key_1 . $key_2_private);

        // $this->SendDebug(__FUNCTION__, 'gateway_password=' . $gateway_password . ', private_password=' . $private_password . ' => ' . $crypt_key_private, 0);
        return $crypt_key_private;
    }

    private function Encrypt($encryptData)
    {
        $key = $this->GetKey();

        $blocksize = 16;
        $encrypt_padchar = $blocksize - (strlen($encryptData) % $blocksize);
        $encryptData .= str_repeat(chr($encrypt_padchar), $encrypt_padchar);

        return base64_encode(
            openssl_encrypt(
                $encryptData,
                'aes-256-ecb',
                hex2bin($key),
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
            )
        );
    }

    private function Decrypt($decryptData)
    {
        $key = $this->GetKey();

        $decrypt = openssl_decrypt(
            base64_decode($decryptData),
            'aes-256-ecb',
            hex2bin($key),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );

        if ($decrypt == '') {
            return false;
        }
        $decrypt = rtrim($decrypt, "\x00");
        if ($decrypt == '') {
            return false;
        }
        $decrypt_len = strlen($decrypt);
        $decrypt_padchar = ord($decrypt[$decrypt_len - 1]);
        for ($i = 0; $i < $decrypt_padchar; $i++) {
            if ($decrypt_padchar != ord($decrypt[$decrypt_len - $i - 1])) {
                break;
            }
        }
        if ($i != $decrypt_padchar) {
            return $decrypt;
        } else {
            return substr(
                $decrypt,
                0,
                $decrypt_len - $decrypt_padchar
            );
        }
    }

    public function GetData(string $datapoint)
    {
        $host = $this->ReadPropertyString('host');
        $port = $this->ReadPropertyInteger('port');

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n" .
                            "User-Agent: TeleHeater/2.2.3\r\n",
            ]
        ];
        $context = stream_context_create($options);
        $this->SendDebug(__FUNCTION__, 'stream_context=' . print_r(stream_context_get_options($context), true), 0);
        $url = 'http://' . $host . ':' . $port . $datapoint;
        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
        $content = @file_get_contents(
            'http://' . $host . ':' . $port . $datapoint,
            false,
            $context
        );
        IPS_Sleep(250);
        if ($content == false) {
            $this->SendDebug(__FUNCTION__, 'got error=' . print_r(error_get_last(), true), 0);
            $this->MaintainStatus(self::$IS_SERVERERROR);
            IPS_SemaphoreLeave($this->SemaphoreID);
            return false;
        }
        $data = $this->Decrypt($content);
        if ($data == false) {
            $this->SendDebug(__FUNCTION__, 'unable to decrypt', 0);
            $this->MaintainStatus(self::$IS_SERVERERROR);
            IPS_SemaphoreLeave($this->SemaphoreID);
            return false;
        }
        $this->MaintainStatus(IS_ACTIVE);
        IPS_SemaphoreLeave($this->SemaphoreID);

        $this->SendDebug(__FUNCTION__, 'decrypt content=' . print_r($data, true), 0);
        return json_decode($data, true);
    }

    private function SetData(string $datapoint, string $content)
    {
        $host = $this->ReadPropertyString('host');
        $port = $this->ReadPropertyInteger('port');

        $this->SendDebug(__FUNCTION__, 'content=' . print_r($content, true), 0);

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $options = [
            'http' => [
                'method'     => 'PUT',
                'header'     => "Content-type: application/json\r\n" .
                                "User-Agent: TeleHeater/2.2.3\r\n",
                'content' => $this->Encrypt($content)
            ]
        ];
        $this->SendDebug(__FUNCTION__, 'options=' . print_r($options, true), 0);
        $context = stream_context_create($options);
        @file_get_contents(
            'http://' . $host . ':' . $port . $datapoint,
            false,
            $context
        );
        IPS_SemaphoreLeave($this->SemaphoreID);
    }

    public function SetBooleanData(string $datapoint, bool $Value)
    {
        return $this->SetData($datapoint, json_encode(['value' => $Value]));
    }

    public function SetIntegerData(string $datapoint, int $Value)
    {
        return $this->SetData($datapoint, json_encode(['value' => $Value]));
    }

    public function SetFloatData(string $datapoint, float $Value)
    {
        return $this->SetData($datapoint, json_encode(['value' => $Value]));
    }

    public function SetStringData(string $datapoint, string $Value)
    {
        return $this->SetData($datapoint, json_encode(['value' => $Value]));
    }
}
