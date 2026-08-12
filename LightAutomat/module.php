<?php

declare(strict_types=1);

/** Generell funktions  */
require_once __DIR__ . '/../libs/_traits.php';

/** Namespaced traits */
use Wilkware\LightAutomat\DebugHelper;
use Wilkware\LightAutomat\EventHelper;
use Wilkware\LightAutomat\VariableHelper;

/**
 *  CLASS LightAutomat
 */
class LightAutomat extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    use DebugHelper;
    use EventHelper;
    use VariableHelper;

    // -------------------------------------------------------------------------
    // Schedule Constant
    // -------------------------------------------------------------------------

    /** @var int Schedule ON */
    public const SCHEDULE_ON = 1;

    /** @var int Schedule OFF */
    public const SCHEDULE_OFF = 2;

    /** @var string Schedule Name */
    public const SCHEDULE_NAME = 'Zeitplan';

    /** @var string Schedule Identifier */
    public const SCHEDULE_IDENT = 'circuit_diagram';

    /** @var array<int,array<mixed>> Schedule Switch */
    public const SCHEDULE_SWITCH = [
        self::SCHEDULE_ON  => ['Aktive', 0x00FF00, "IPS_RequestAction(\$_IPS['TARGET'], 'circuit_diagram', \$_IPS['ACTION']);"],
        self::SCHEDULE_OFF => ['Inaktive', 0xFF0000, "IPS_RequestAction(\$_IPS['TARGET'], 'circuit_diagram', \$_IPS['ACTION']);"],
    ];

    // -------------------------------------------------------------------------
    // Time Units Constant
    // -------------------------------------------------------------------------

    /** @var int Time in Seconds */
    public const TIME_SECONDS = 0;

    /** @var int Time in Minutes */
    public const TIME_MINUTES = 1;

    /** @var int Time in Hours */
    public const TIME_HOURS = 2;

    /** @var int Time in Clock Format */
    public const TIME_CLOCK = 3;

    /** @var array<int,array<string,int|string>> Time Units */
    public const TIME_UNIT = [
        self::TIME_SECONDS => ['suffix' => ' seconds', 'min' => 1, 'max' => 59, 'factor' => 1],
        self::TIME_MINUTES => ['suffix' => ' minutes', 'min' => 1, 'max' => 59, 'factor' => 60],
        self::TIME_HOURS   => ['suffix' => ' hours',   'min' => 1, 'max' => 23, 'factor' => 3600],
        self::TIME_CLOCK   => [], // keine Spinner-Werte nötig, eigenes Formularelement (Time)
    ];

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** @var int Device Type: Single */
    private const DEVICE_ONE = 0;

    /** @var int Device Type: Multiple */
    private const DEVICE_MULTIPLE = 1;

    /** @var int Min IPS Object ID */
    private const IPS_MIN_ID = 10000;

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
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

        // Tracks the TimeUnit that duty_cycle was last initialized with,
        // so ApplyChanges can detect a unit change and recalculate the value.
        $this->RegisterAttributeInteger('LastTimeUnit', -1);

        // Timer
        $this->RegisterTimer('TLA.Timer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "delay_trigger", "");');
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     *
     * @return void
     */
    public function Destroy(): void
    {
        parent::Destroy();
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
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
                    $this->LogDebug(__FUNCTION__, 'Variable does not exist: ' . $variable);
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
                            $this->LogDebug(__FUNCTION__, 'Variable(s) does not exist: ' . $variable['VariableID']);
                            $this->SetStatus(104);
                            return;
                        }
                    } else {
                        $this->LogDebug(__FUNCTION__, 'Variable(s) does not exist: ' . $variable['VariableID']);
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
                $this->LogDebug(__FUNCTION__, 'Motion variable does not exist: ' . $variable);
                $this->SetStatus(104);
                return;
            }
        }
        $event = $this->ReadPropertyInteger('EventVariable');
        if ($event >= self::IPS_MIN_ID) {
            if (IPS_EventExists($event)) {
                $this->RegisterReference($event);
            } else {
                $this->LogDebug(__FUNCTION__, 'Event does not exist: ' . $event);
                $this->SetStatus(104);
                return;
            }
        }
        $script = $this->ReadPropertyInteger('ScriptVariable');
        if ($script >= self::IPS_MIN_ID) {
            if (IPS_ScriptExists($script)) {
                $this->RegisterReference($script);
            } else {
                $this->LogDebug(__FUNCTION__, 'Script does not exist: ' . $script);
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
        $this->MaintainVariable('continuous_operation', $this->Translate('Continuous operation'), VARIABLETYPE_BOOLEAN, ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH], 0, $permanent);
        if ($permanent) {
            $this->SetValueBoolean('continuous_operation', false);
            $this->EnableAction('continuous_operation');
        }

        // Detect a TimeUnit change via attribute (works reliably across the
        // structurally different Slider / Datum-Uhrzeit presentations)
        $duration = $this->ReadPropertyBoolean('CheckDuration');
        $unit = $this->ReadPropertyInteger('TimeUnit');
        $lastUnit = $this->ReadAttributeInteger('LastTimeUnit');
        $unitChanged = ($lastUnit !== $unit);

        if ($unit < self::TIME_CLOCK) {
            // Sekunden/Minuten/Stunden: Schieberegler, actionfähig, kein Profil,
            // Wert bleibt unverändert in der gewählten Einheit (keine Umrechnung)
            $created = $this->MaintainVariable(
                'duty_cycle',
                $this->Translate('Duty cycle'),
                VARIABLETYPE_INTEGER,
                [
                    'PRESENTATION'  => VARIABLE_PRESENTATION_SLIDER,
                    'MIN'           => self::TIME_UNIT[$unit]['min'],
                    'MAX'           => self::TIME_UNIT[$unit]['max'],
                    'SUFFIX'        => $this->Translate(self::TIME_UNIT[$unit]['suffix']),
                    'STEP_SIZE'     => 1.0,
                    'ICON'          => 'clock',
                ],
                1,
                $duration
            );
        } else {
            // Uhrzeit: Datum/Uhrzeit-Darstellung (Stunde, Minute, Sekunde), ebenfalls actionfähig
            $created = $this->MaintainVariable(
                'duty_cycle',
                $this->Translate('Duty cycle'),
                VARIABLETYPE_INTEGER,
                [
                    'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
                    'DATE'         => 0,
                    'TIME'         => 2,
                    'ICON'         => 'clock',
                ],
                1,
                $duration
            );
        }

        $this->LogDebug(__FUNCTION__, 'Create duration: ' . $duration . ' Create permanent: ' . $permanent);

        if ($duration) {
            // Nur bei Neuanlage ODER Wechsel der TimeUnit neu berechnen
            if ($created || $unitChanged) {
                if ($unit < self::TIME_CLOCK) {
                    $this->SetValueInteger('duty_cycle', $this->ReadPropertyInteger('Duration'));
                } else {
                    $time = json_decode($this->ReadPropertyString('Time'), true);
                    // Same 82800s offset the module always used for the clock-based raw value -
                    // kept unchanged since we cannot verify locally whether it compensates
                    // for a timezone effect in the displayed value.
                    $this->SetValueInteger('duty_cycle', 82800 + ($time['hour'] * 3600) + ($time['minute'] * 60) + $time['second']);
                }
            }
            $this->EnableAction('duty_cycle');
        }
        $this->WriteAttributeInteger('LastTimeUnit', $unit);
        $this->SetStatus(102);
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return string Content of the configuration page.
     */
    public function GetConfigurationForm(): string
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
        $this->LogDebug(__FUNCTION__, 'unit=' . $unit);

        // Set duration inputs
        if ($unit < self::TIME_CLOCK) {
            $suf = $this->Translate(self::TIME_UNIT[$unit]['suffix']);
            $min = self::TIME_UNIT[$unit]['min'];
            $max = self::TIME_UNIT[$unit]['max'];

            $form['elements'][3]['items'][0]['items'][1]['minimum'] = $min;
            $form['elements'][3]['items'][0]['items'][1]['maximum'] = $max;
            $form['elements'][3]['items'][0]['items'][1]['suffix'] = $suf;
            $form['elements'][3]['items'][0]['items'][1]['visible'] = true;
        } else {
            $form['elements'][3]['items'][0]['items'][2]['visible'] = true;
        }

        // Debug output
        //$this->LogDebug(__FUNCTION__, $form);
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
     * @param int   $timestamp Continuous counter timestamp
     * @param int   $sender    Sender ID
     * @param int   $message   ID of the message
     * @param array{0:mixed,1:bool,2:mixed,3:int} $data Data of the message
     *
     * @return void
     */
    public function MessageSink(int $timestamp, int $sender, int $message, array $data): void
    {
        //$this->LogDebug(__FUNCTION__, 'SenderId: ' . $sender . ' Data: ' . print_r($data, true), 0);
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
                    $this->LogDebug(__FUNCTION__, 'SenderID: ' . $sender . ' unknown!');
                    break;
                }

                // Countinus operation?
                $permanent = $this->ReadPropertyBoolean('CheckPermanent');
                if ($permanent) {
                    $state = $this->GetValue('continuous_operation');
                    if ($state) {
                        $this->LogDebug(__FUNCTION__, 'Continuous operation is ON!');
                        break;
                    }
                }

                // Weekly schedule!
                $eid = $this->ReadPropertyInteger('EventVariable');
                if ($eid != 0) {
                    $state = $this->GetWeeklyScheduleInfo($eid);
                    if ($state['WeekPlanActiv'] == 1 && $state['ActionID'] == 2) {
                        $this->LogDebug(__FUNCTION__, 'Weekly schedule is stored but state is inaktive!');
                        break;
                    }
                }

                // Check of change
                if ($data[0] == true && $data[1] == true) {
                    // OnChange is TRUE => switched ON
                    $this->LogDebug(__FUNCTION__, 'OnChange #' . $sender . ' is TRUE - ON');
                    $this->SetTimerInterval('TLA.Timer', $this->CalculateTimer());
                } elseif ($data[0] == false && $data[1] == true) {
                    // OnChange is FALSE => switched OFF
                    $this->LogDebug(__FUNCTION__, 'OnChange #' . $sender . ' is FALSE - OFF');
                    $this->SetTimerInterval('TLA.Timer', 0);
                } else {
                    // OnChange - no chenges!
                    // $this->LogDebug(__FUNCTION__, 'OnChange - nothing changed!');
                }
                break;
        }
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
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
                // Ident == OnXxxxxYyyyy
                eval('$this->' . $ident . '(\'' . $value . '\');');
        }
    }

    /**
     * Import death days data.
     *
     * @param string $value unit and value of duration.
     *
     * @return void
     */
    protected function OnTimeUnit(string $value): void
    {
        $this->LogDebug(__FUNCTION__, $value);
        $data = unserialize($value);

        if ($data['unit'] < self::TIME_CLOCK) {
            // min/max/suffix
            $suf = $this->Translate(self::TIME_UNIT[$data['unit']]['suffix']);
            $min = self::TIME_UNIT[$data['unit']]['min'];
            $max = self::TIME_UNIT[$data['unit']]['max'];

            // Set min/max/suffix
            $this->UpdateFormField('Duration', 'minimum', $min);
            $this->UpdateFormField('Duration', 'maximum', $max);
            $this->UpdateFormField('Duration', 'suffix', $suf);

            // Check Value
            $value = $data['value'];
            if ($value > $max) {
                $value = 10; // default: 10
                $this->UpdateFormField('Duration', 'value', $value);
            }
        }

        $this->UpdateFormField('Duration', 'visible', ($data['unit'] != self::TIME_CLOCK));
        $this->UpdateFormField('Time', 'visible', ($data['unit'] == self::TIME_CLOCK));
    }

    /**
     * Creates a schedule plan.
     *
     * @param string $value instance ID.
     *
     * @return void
     */
    protected function OnCreateSchedule(string $value): void
    {
        $eid = $this->CreateWeeklySchedule($this->InstanceID, self::SCHEDULE_NAME, self::SCHEDULE_IDENT, self::SCHEDULE_SWITCH, -1);
        if (IPS_EventExists($eid)) {
            $this->UpdateFormField('EventVariable', 'value', $eid);
        }
    }

    /**
     * User has select an new number of devices.
     *
     * @param string $value select value.
     *
     * @return void
     */
    protected function OnDeviceNumber(string $value): void
    {
        $this->LogDebug(__FUNCTION__, 'Value: ' . $value);
        $this->UpdateFormField('StateVariable', 'visible', ($value == self::DEVICE_ONE));
        $this->UpdateFormField('StateVariables', 'visible', ($value == self::DEVICE_MULTIPLE));
    }

    /**
     * Trigger Timer
     *
     * @return void
     */
    private function Trigger(): void
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
                    $this->LogDebug(__FUNCTION__, 'Motion detection aktive, still resume!');
                    return;
                } else {
                    foreach ($shift as $var) {
                        if (HasAction($var)) {
                            $ret = @RequestAction($var, false);
                            if ($ret === false) {
                                $this->LogDebug(__FUNCTION__, 'Device #' . $var . ' could not be switched by RequestAction!');
                            }
                        } else {
                            $ret = @SetValueBoolean($var, false);
                            if ($ret === false) {
                                $this->LogDebug(__FUNCTION__, 'Device could not be switched by Boolean!');
                            }
                        }
                        if ($ret === false) {
                            $this->LogMessage('Device could not be switched (UNREACH)!');
                        } else {
                            $this->LogDebug(__FUNCTION__, 'Variable #' . $var . ' switched to FALSE!');
                        }
                    }
                }
            }
            // Script ausführen
            if ($this->ReadPropertyInteger('ScriptVariable') != 0) {
                if (IPS_ScriptExists($this->ReadPropertyInteger('ScriptVariable'))) {
                    $rs = IPS_RunScript($this->ReadPropertyInteger('ScriptVariable'));
                    $this->LogDebug(__FUNCTION__, 'Script Execute Return Value: ' . $rs);
                } else {
                    $this->LogDebug(__FUNCTION__, 'Script #' . $this->ReadPropertyInteger('ScriptVariable') . ' does not exist!');
                }
            }
        } else {
            $this->LogDebug(__FUNCTION__, 'STATE already on FALSE - delete Timer!');
        }
        $this->SetTimerInterval('TLA.Timer', 0);
    }

    /**
     * Schedule Event
     *
     * @param int $value Action value (ON=1, OFF=2)
     *
     * @return void
     */
    private function Schedule(int $value): void
    {
        $this->LogDebug(__FUNCTION__, 'Value: ' . $value);

        // Check SChedule on Activate?
        $check = $this->ReadPropertyBoolean('CheckSchedule');
        if (!$check) {
            $this->LogDebug(__FUNCTION__, 'Check: nothing to do!');
            return;
        }

        // Is Activate ON
        if ($value == self::SCHEDULE_OFF) {
            $this->LogDebug(__FUNCTION__, 'Value: nothing to do!');
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
            $this->LogDebug(__FUNCTION__, 'Timer: ' . $interval);
            if ($interval == 0) {
                $this->LogDebug(__FUNCTION__, 'State is TRUE and no Timer ON');
                $this->SetTimerInterval('TriggerTimer', $this->CalculateTimer());
            }
        }
    }

    /**
     * Calculate duration timer.
     *
     * @return int Timer intervall in milliseconds
     */
    private function CalculateTimer(): int
    {
        $interval = 0;
        $unit = $this->ReadPropertyInteger('TimeUnit');

        // Use internal or external variable
        $useVariable = $this->ReadPropertyBoolean('CheckDuration');

        if ($useVariable) {
            // duty_cycle is stored in the currently selected unit (Slider) or as
            // a real clock time (Datum/Uhrzeit) - no unit-independent shortcut possible
            if ($unit < self::TIME_CLOCK) {
                $time = $this->GetValue('duty_cycle');
                $interval = 1000 * self::TIME_UNIT[$unit]['factor'] * $time;
            } else {
                $vid = $this->GetIDForIdent('duty_cycle');
                $time = explode(':', GetValueFormatted($vid));
                $interval = 1000 * (intval($time[0]) * 3600 + (intval($time[1]) * 60) + intval($time[2]));
            }
        } else {
            // The "Duration" or "Time" property is available in the selected unit
            if ($unit < self::TIME_CLOCK) {
                $time = $this->ReadPropertyInteger('Duration');
                $interval = 1000 * self::TIME_UNIT[$unit]['factor'] * $time;
            } else {
                $time = json_decode($this->ReadPropertyString('Time'), true);
                $interval = 1000 * (intval($time['hour']) * 3600 + (intval($time['minute']) * 60) + intval($time['second']));
            }
        }

        return $interval;
    }

    /**
     * Received the status of a given variable
     *
     * @param int $vid variable ID.
     *
     * @return string Status message
     */
    private function GetVariableStatus(int $vid): string
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
}
