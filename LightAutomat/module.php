<?php

declare(strict_types=1);

// General helper functions
require_once __DIR__ . '/../libs/_traits.php';

/**
 * CLASS LightAutomat
 */
class LightAutomat extends IPSModule
{
    use DebugHelper;
    use EventHelper;
    use ProfileHelper;
    use VariableHelper;

    // Schedule constant
    public const SCHEDULE_ON = 1;
    public const SCHEDULE_OFF = 2;
    public const SCHEDULE_NAME = 'Zeitplan';
    public const SCHEDULE_IDENT = 'circuit_diagram';
    public const SCHEDULE_SWITCH = [
        self::SCHEDULE_ON  => ['Aktive', 0x00FF00, "IPS_RequestAction(\$_IPS['TARGET'], 'circuit_diagram', \$_IPS['ACTION']);"],
        self::SCHEDULE_OFF => ['Inaktive', 0xFF0000, "IPS_RequestAction(\$_IPS['TARGET'], 'circuit_diagram', \$_IPS['ACTION']);"],
    ];
    // Time Unites constant
    public const TIME_SECONDS = 0;
    public const TIME_MINUTES = 1;
    public const TIME_HOURS = 2;
    public const TIME_CLOCK = 3;
    public const TIME_UNIT = [
        self::TIME_SECONDS  => ['TLA.Seconds', ' seconds', 1, 59, 1],
        self::TIME_MINUTES  => ['TLA.Minutes', ' minutes', 1, 59, 60],
        self::TIME_HOURS    => ['TLA.Hours', ' hours', 1, 23, 3600],
        self::TIME_CLOCK    => ['~UnixTimestampTime', '', 1, 23, 82800],
    ];

    // Devices constant
    private const DEVICE_ONE = 0;
    private const DEVICE_MULTIPLE = 1;

    // Min IPS Object ID
    private const IPS_MIN_ID = 10000;

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        // Devices ...
        $this->RegisterPropertyInteger('DeviceNumber', 0);
        $this->RegisterPropertyInteger('StateVariable', 0);
        $this->RegisterPropertyString('StateVariables', '[]');
        $this->RegisterPropertyInteger('MotionVariable', 0);
        // Time Control ...
        $this->RegisterPropertyInteger('TimeUnit', 1);
        $this->RegisterPropertyInteger('Duration', 10);
        $this->RegisterPropertyString('Time', '{"hour":0,"minute":1,"second":0}');
        $this->RegisterPropertyInteger('EventVariable', 0);
        // Advanced Settings ...
        $this->RegisterPropertyInteger('ScriptVariable', 0);
        $this->RegisterPropertyBoolean('OnlyScript', false);
        $this->RegisterPropertyBoolean('CheckSchedule', true);
        $this->RegisterPropertyBoolean('CheckDuration', true);
        $this->RegisterPropertyBoolean('CheckPermanent', true);
        // Profile
        foreach (self::TIME_UNIT as $key => $value) {
            if ($key != self::TIME_CLOCK) {
                $this->RegisterProfileInteger($value[0], 'Clock', '', $this->Translate($value[1]), $value[2], $value[3], 1, null);
            }
        }
        // Timer
        $this->RegisterTimer('TLA.Timer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "delay_trigger", "");');
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     */
    public function Destroy()
    {
        parent::Destroy();
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        //Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $reference) {
            $this->UnregisterReference($reference);
        }

        //Delete all registrations in order to readd them
        foreach ($this->GetMessageList() as $sender => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($sender, $message);
            }
        }

        //Register references
        $devices = $this->ReadPropertyInteger('DeviceNumber');
        if ($devices == self::DEVICE_ONE) {
            $variable = $this->ReadPropertyInteger('StateVariable');
            if ($variable >= self::IPS_MIN_ID) {
                if (IPS_VariableExists($variable)) {
                    $this->RegisterReference($variable);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Variable does not exist: ' . $variable);
                    $this->SetStatus(104);
                    return;
                }
            }
        } else {
            $variables = json_decode($this->ReadPropertyString('StateVariables'), true);
            foreach ($variables as $variable) {
                if ($variable['VariableID'] >= self::IPS_MIN_ID) {
                    if (IPS_VariableExists($variable['VariableID'])) {
                        $this->RegisterReference($variable['VariableID']);
                        if ($this->GetVariableStatus($variable['VariableID']) != 'OK') {
                            $this->SendDebug(__FUNCTION__, 'Variable(s) does not exist: ' . $variable['VariableID']);
                            $this->SetStatus(104);
                            return;
                        }
                    } else {
                        $this->SendDebug(__FUNCTION__, 'Variable(s) does not exist: ' . $variable['VariableID']);
                        $this->SetStatus(104);
                        return;
                    }
                }
            }
        }
        $variable = $this->ReadPropertyInteger('MotionVariable');
        if ($variable >= self::IPS_MIN_ID) {
            if (IPS_VariableExists($variable)) {
                $this->RegisterReference($variable);
            } else {
                $this->SendDebug(__FUNCTION__, 'Motion variable does not exist: ' . $variable);
                $this->SetStatus(104);
                return;
            }
        }
        $event = $this->ReadPropertyInteger('EventVariable');
        if ($event >= self::IPS_MIN_ID) {
            if (IPS_EventExists($event)) {
                $this->RegisterReference($event);
            } else {
                $this->SendDebug(__FUNCTION__, 'Event does not exist: ' . $event);
                $this->SetStatus(104);
                return;
            }
        }
        $script = $this->ReadPropertyInteger('ScriptVariable');
        if ($script >= self::IPS_MIN_ID) {
            if (IPS_ScriptExists($script)) {
                $this->RegisterReference($script);
            } else {
                $this->SendDebug(__FUNCTION__, 'Script does not exist: ' . $script);
                $this->SetStatus(104);
                return;
            }
        }

        // Register messages update  = Create our trigger
        if ($devices == self::DEVICE_ONE) {
            $variable = $this->ReadPropertyInteger('StateVariable');
            $this->RegisterMessage($variable, VM_UPDATE);
        } else {
            $variables = json_decode($this->ReadPropertyString('StateVariables'), true);
            foreach ($variables as $variable) {
                $this->RegisterMessage($variable['VariableID'], VM_UPDATE);
            }
        }

        // Maintain variables
        $permanent = $this->ReadPropertyBoolean('CheckPermanent');
        $this->MaintainVariable('continuous_operation', $this->Translate('Continuous operation'), VARIABLETYPE_BOOLEAN, '~Switch', 0, $permanent);
        if ($permanent) {
            $this->SetValueBoolean('continuous_operation', false);
            $this->EnableAction('continuous_operation');
        }
        $duration = $this->ReadPropertyBoolean('CheckDuration');
        $unit = $this->ReadPropertyInteger('TimeUnit');
        $this->MaintainVariable('duty_cycle', $this->Translate('Duty cycle'), VARIABLETYPE_INTEGER, self::TIME_UNIT[$unit][0], 1, $duration);
        $this->SendDebug(__FUNCTION__, 'Create duration: ' . $duration . ' Create perament: ' . $permanent, 0);
        if ($duration) {
            if ($unit < self::TIME_CLOCK) {
                $time = $this->ReadPropertyInteger('Duration');
                $this->SetValueInteger('duty_cycle', $time);
            } else {
                $time = json_decode($this->ReadPropertyString('Time'), true);
                $this->SetValueInteger('duty_cycle', 82800 + (($time['hour'] * 3600) + ($time['minute'] * 60) + $time['second']));
            }
            $this->EnableAction('duty_cycle');
        }
        $this->SetStatus(102);
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return JSON Content of the configuration page
     */
    public function GetConfigurationForm()
    {
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // number of devices
        $devices = $this->ReadPropertyInteger('DeviceNumber');
        $form['elements'][2]['items'][1]['visible'] = ($devices === self::DEVICE_ONE);
        $form['elements'][2]['items'][2]['visible'] = ($devices === self::DEVICE_MULTIPLE);
        // device list (set status column)
        $variables = json_decode($this->ReadPropertyString('StateVariables'), true);
        foreach ($variables as $variable) {
            $form['elements'][2]['items'][2]['values'][] = [
                'Status' => $this->GetVariableStatus($variable['VariableID']),
            ];
        }
        // time setup
        $unit = $this->ReadPropertyInteger('TimeUnit');
        // Debug output
        $this->SendDebug(__FUNCTION__, 'unit=' . $unit);
        // Set duration inputs
        if ($unit < self::TIME_CLOCK) {
            // Check duration
            $suf = $this->Translate(self::TIME_UNIT[$unit][1]);
            $min = self::TIME_UNIT[$unit][2];
            $max = self::TIME_UNIT[$unit][3];
            // Set min/max/suffix
            $form['elements'][3]['items'][0]['items'][1]['minimum'] = $min;
            $form['elements'][3]['items'][0]['items'][1]['maximum'] = $max;
            $form['elements'][3]['items'][0]['items'][1]['suffix'] = $suf;
            $form['elements'][3]['items'][0]['items'][1]['visible'] = true;
        } else {
            $form['elements'][3]['items'][0]['items'][2]['visible'] = true;
        }
        // Debug output
        //$this->SendDebug(__FUNCTION__, $form);
        return json_encode($form);
    }

    /**
     * The content of the function can be overwritten in order to carry out own reactions to certain messages.
     * The function is only called for registered MessageIDs/SenderIDs combinations.
     *
     * data[0] = new value
     * data[1] = value changed?
     * data[2] = old value
     * data[3] = timestamp.
     *
     * @param mixed $timestamp Continuous counter timestamp
     * @param mixed $sender Sender ID
     * @param mixed $message ID of the message
     * @param mixed $data Data of the message
     */
    public function MessageSink($timestamp, $sender, $message, $data)
    {
        //$this->SendDebug(__FUNCTION__, 'SenderId: ' . $sender . ' Data: ' . print_r($data, true), 0);
        switch ($message) {
            case VM_UPDATE:
                // single or multiple
                $variable = 0;
                $devices = $this->ReadPropertyInteger('DeviceNumber');
                if ($devices == self::DEVICE_ONE) {
                    $variable = $this->ReadPropertyInteger('StateVariable');
                } else {
                    $variables = json_decode($this->ReadPropertyString('StateVariables'), true);
                    foreach ($variables as $var) {
                        if ($var['VariableID'] == $sender) {
                            $variable = $var['VariableID'];
                            break;
                        }
                    }
                }
                // Safty Check
                if ($sender !== $variable) {
                    $this->SendDebug(__FUNCTION__, 'SenderID: ' . $sender . ' unknown!');
                    break;
                }
                // Countinus operation?
                $permanent = $this->ReadPropertyBoolean('CheckPermanent');
                if ($permanent) {
                    $state = $this->GetValue('continuous_operation');
                    if ($state) {
                        $this->SendDebug(__FUNCTION__, 'Continuous operation is ON!');
                        break;
                    }
                }
                // Weekly schedule!
                $eid = $this->ReadPropertyInteger('EventVariable');
                if ($eid != 0) {
                    $state = $this->GetWeeklyScheduleInfo($eid);
                    if ($state['WeekPlanActiv'] == 1 && $state['ActionID'] == 2) {
                        $this->SendDebug(__FUNCTION__, 'Weekly schedule is stored but state is inaktive!');
                        break;
                    }
                }
                // Check of change
                if ($data[0] == true && $data[1] == true) {
                    // OnChange is TRUE => switched ON
                    $this->SendDebug(__FUNCTION__, 'OnChange #' . $sender . ' is TRUE - ON');
                    $this->SetTimerInterval('TLA.Timer', $this->CalculateTimer());
                } elseif ($data[0] == false && $data[1] == true) {
                    // OnChange is FALSE => switched OFF
                    $this->SendDebug(__FUNCTION__, 'OnChange #' . $sender . ' is FALSE - OFF');
                    $this->SetTimerInterval('TLA.Timer', 0);
                } else {
                    // OnChange - no chenges!
                    // $this->SendDebug(__FUNCTION__, 'OnChange - nothing changed!');
                }
                break;
        }
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     *  @param string $ident Ident of the variable
     *  @param string $value The value to be set
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        // Ident == OnXxxxxYyyyy
        switch ($ident) {
            case 'continuous_operation':
                $this->SetValueBoolean($ident, $value);
                break;
            case 'duty_cycle':
                $this->SetValueInteger($ident, $value);
                break;
            case 'circuit_diagram':
                $this->Schedule($value);
                break;
            case 'delay_trigger':
                $this->Trigger();
                break;
            default:
                eval('$this->' . $ident . '(\'' . $value . '\');');
        }
        //return true;
    }

    /**
     * Import death days data.
     *
     * @param string $value unit and value of duration.
     */
    protected function OnTimeUnit($value)
    {
        $this->SendDebug(__FUNCTION__, $value);
        $data = unserialize($value);
        if ($data['unit'] < self::TIME_CLOCK) {
            // min/max/suffix
            $suf = $this->Translate(self::TIME_UNIT[$data['unit']][1]);
            $min = self::TIME_UNIT[$data['unit']][2];
            $max = self::TIME_UNIT[$data['unit']][3];
            // Set min/max/suffix
            $this->UpdateFormField('Duration', 'minimum', $min);
            $this->UpdateFormField('Duration', 'maximum', $max);
            $this->UpdateFormField('Duration', 'suffix', $suf);
            // Check Value
            $value = $data['value'];
            if ($value > $max) {
                $value = 10; //default: 10
                $this->UpdateFormField('Duration', 'value', $value);
            }
        }
        $this->UpdateFormField('Duration', 'visible', ($data['unit'] != self::TIME_CLOCK));
        $this->UpdateFormField('Time', 'visible', ($data['unit'] == self::TIME_CLOCK));
    }

    /**
     * Trigger Timer
     *
     */
    private function Trigger()
    {
        $shift = [];
        // One or more devices?
        $devices = $this->ReadPropertyInteger('DeviceNumber');
        if ($devices == self::DEVICE_ONE) {
            $variable = $this->ReadPropertyInteger('StateVariable');
            if (($variable >= self::IPS_MIN_ID) && (IPS_VariableExists($variable))) {
                if (GetValueBoolean($variable) == true) {
                    $shift[] = $variable;
                }
            }
        } else {
            $variables = json_decode($this->ReadPropertyString('StateVariables'), true);
            foreach ($variables as $variable) {
                if (($variable['VariableID'] >= self::IPS_MIN_ID) && (IPS_VariableExists($variable['VariableID']))) {
                    if (GetValueBoolean($variable['VariableID']) == true) {
                        $shift[] = $variable['VariableID'];
                    }
                }
            }
        }
        if (!empty($shift)) {
            if ($this->ReadPropertyBoolean('OnlyScript') == false) {
                $mid = $this->ReadPropertyInteger('MotionVariable');
                if ($mid != 0 && GetValue($mid)) {
                    $this->SendDebug(__FUNCTION__, 'Motion detection aktive, still resume!');
                    return;
                } else {
                    foreach ($shift as $var) {
                        if (HasAction($var)) {
                            $ret = @RequestAction($var, false);
                            if ($ret === false) {
                                $this->SendDebug(__FUNCTION__, 'Device #' . $var . ' could not be switched by RequestAction!');
                            }
                        } else {
                            $ret = @SetValueBoolean($var, false);
                            if ($ret === false) {
                                $this->SendDebug(__FUNCTION__, 'Device could not be switched by Boolean!');
                            }
                        }
                        if ($ret === false) {
                            $this->LogMessage('Device could not be switched (UNREACH)!');
                        } else {
                            $this->SendDebug(__FUNCTION__, 'Variable #' . $var . ' switched to FALSE!');
                        }
                    }
                }
            }
            // Script ausführen
            if ($this->ReadPropertyInteger('ScriptVariable') != 0) {
                if (IPS_ScriptExists($this->ReadPropertyInteger('ScriptVariable'))) {
                    $rs = IPS_RunScript($this->ReadPropertyInteger('ScriptVariable'));
                    $this->SendDebug(__FUNCTION__, 'Script Execute Return Value: ' . $rs);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Script #' . $this->ReadPropertyInteger('ScriptVariable') . ' does not exist!');
                }
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'STATE already on FALSE - delete Timer!');
        }
        $this->SetTimerInterval('TLA.Timer', 0);
    }

    /**
     * Schedule Event
     *
     * @param integer $vaue Action value (ON=1, OFF=2)
     */
    private function Schedule(int $value)
    {
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value);
        // Check SChedule on Activate?
        $check = $this->ReadPropertyBoolean('CheckSchedule');
        if (!$check) {
            $this->SendDebug(__FUNCTION__, 'Check: nothing to do!');
            return;
        }
        // Is Activate ON
        if ($value == self::SCHEDULE_OFF) {
            $this->SendDebug(__FUNCTION__, 'Value: nothing to do!');
            return;
        }
        // Is a device ON
        $shift = [];
        // One or more devices?
        $devices = $this->ReadPropertyInteger('DeviceNumber');
        if ($devices == self::DEVICE_ONE) {
            $variable = $this->ReadPropertyInteger('StateVariable');
            if (($variable >= self::IPS_MIN_ID) && (IPS_VariableExists($variable))) {
                if (GetValueBoolean($variable) == true) {
                    $shift[] = $variable;
                }
            }
        } else {
            $variables = json_decode($this->ReadPropertyString('StateVariables'), true);
            foreach ($variables as $variable) {
                if (($variable['VariableID'] >= self::IPS_MIN_ID) && (IPS_VariableExists($variable['VariableID']))) {
                    if (GetValueBoolean($variable['VariableID']) == true) {
                        $shift[] = $variable['VariableID'];
                    }
                }
            }
        }
        if (!empty($shift)) {
            // Is a Timer active
            $interval = $this->GetTimerInterval('TriggerTimer');
            $this->SendDebug(__FUNCTION__, 'Timer: ' . $interval);
            if ($interval == 0) {
                $this->SendDebug(__FUNCTION__, 'State is TRUE and no Timer ON');
                $this->SetTimerInterval('TriggerTimer', $this->CalculateTimer());
            }
        }
    }

    /**
     * Calculate duration timer.
     *
     * @return int   Timer intervall in milliseconds
     */
    private function CalculateTimer()
    {
        $interval = 0;
        $unit = $this->ReadPropertyInteger('TimeUnit');
        // Use internal or external variable
        $duration = $this->ReadPropertyBoolean('CheckDuration');
        if ($duration) {
            if ($unit < self::TIME_CLOCK) {
                $time = $this->GetValue('duty_cycle');
                $interval = 1000 * self::TIME_UNIT[$unit][4] * $time;
            } else {
                $vid = $this->GetIDForIdent('duty_cycle');
                $time = explode(':', GetValueFormatted($vid));
                $interval = 1000 * (($time[0] * 3600) + ($time[1] * 60) + $time[2]);
            }
        } else {
            if ($unit < self::TIME_CLOCK) {
                $time = $this->ReadPropertyInteger('Duration');
                $interval = 1000 * self::TIME_UNIT[$unit][4] * $time;
            } else {
                $time = json_decode($this->ReadPropertyString('Time'), true);
                $interval = 1000 * (($time[0] * 3600) + ($time[1] * 60) + $time[2]);
            }
        }
        return $interval;
    }

    /**
     * Received the status of a given variable
     *
     * @param int $vid variable ID.
     * @return string Status message
     */
    private function GetVariableStatus($vid)
    {
        if (!IPS_VariableExists($vid)) {
            return $this->Translate('Missing');
        } else {
            $var = IPS_GetVariable($vid);
            switch ($var['VariableType']) {
                case VARIABLETYPE_BOOLEAN:
                    if ($var['VariableCustomProfile'] != '') {
                        $profile = $var['VariableCustomProfile'];
                    } else {
                        $profile = $var['VariableProfile'];
                    }
                    if (!IPS_VariableProfileExists($profile)) {
                        return $this->Translate('Profile required');
                    }
                    if ($var['VariableCustomAction'] != 0) {
                        $action = $var['VariableCustomAction'];
                    } else {
                        $action = $var['VariableAction'];
                    }
                    if (!($action > self::IPS_MIN_ID)) {
                        return $this->Translate('Action required');
                    }
                    return 'OK';
                default:
                    return $this->Translate('Bool required');
            }
        }
    }

    /**
     * Creates a schedule plan.
     *
     * @param string $value instance ID.
     */
    private function OnCreateSchedule($value)
    {
        $eid = $this->CreateWeeklySchedule($this->InstanceID, self::SCHEDULE_NAME, self::SCHEDULE_IDENT, self::SCHEDULE_SWITCH, -1);
        if ($eid !== false) {
            $this->UpdateFormField('EventVariable', 'value', $eid);
        }
    }

    /**
     * User has select an new number of devices.
     *
     * @param string $value select value.
     */
    private function OnDeviceNumber($value)
    {
        $this->SendDebug(__FUNCTION__, 'Value: ' . $value);
        $this->UpdateFormField('StateVariable', 'visible', ($value == self::DEVICE_ONE));
        $this->UpdateFormField('StateVariables', 'visible', ($value == self::DEVICE_MULTIPLE));
    }
}
