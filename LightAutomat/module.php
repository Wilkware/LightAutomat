<?php

declare(strict_types=1);

// General helper functions
require_once __DIR__ . '/../libs/_traits.php';

// Namespaced traits
use Wilkware\LightAutomat\DebugHelper;
use Wilkware\LightAutomat\EventHelper;
use Wilkware\LightAutomat\FormHelper;
use Wilkware\LightAutomat\VariableHelper;

/**
 * CLASS LightAutomat
 */
class LightAutomat extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    use DebugHelper;
    use EventHelper;
    use FormHelper;
    use VariableHelper;

    // -------------------------------------------------------------------------
    // Schedule Constants
    // -------------------------------------------------------------------------

    /** @var int Schedule ON */
    public const SCHEDULE_ON = 1;

    /** @var int Schedule OFF */
    public const SCHEDULE_OFF = 2;

    /** @var string Schedule Name */
    public const SCHEDULE_NAME = 'Schedule';

    /** @var string Schedule Identifier */
    public const SCHEDULE_IDENT = 'circuit_diagram';

    /** @var array<int,array<mixed>> Schedule Switch */
    public const SCHEDULE_SWITCH = [
        self::SCHEDULE_ON  => ['Active', 0x00FF00, "IPS_RequestAction(\$_IPS['TARGET'], 'circuit_diagram', \$_IPS['ACTION']);"],
        self::SCHEDULE_OFF => ['Inactive', 0xFF0000, "IPS_RequestAction(\$_IPS['TARGET'], 'circuit_diagram', \$_IPS['ACTION']);"],
    ];

    // -------------------------------------------------------------------------
    // Time Unit Constants
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
        self::TIME_CLOCK   => [], // no spinner values, uses its own form element (SelectTime)
    ];

    // -------------------------------------------------------------------------
    // Status Constants
    // -------------------------------------------------------------------------

    /** @var int Switch variable missing */
    private const STATUS_VARIABLE_MISSING = 201;

    /** @var int Switch variable not switchable */
    private const STATUS_VARIABLE_INVALID = 202;

    /** @var int Motion variable missing */
    private const STATUS_MOTION_MISSING = 203;

    /** @var int Weekly schedule missing */
    private const STATUS_SCHEDULE_MISSING = 204;

    /** @var int Script missing */
    private const STATUS_SCRIPT_MISSING = 205;

    /** @var int Only script without a script */
    private const STATUS_SCRIPT_REQUIRED = 206;

    // -------------------------------------------------------------------------
    // Presentation Constants
    // -------------------------------------------------------------------------

    /** @var array<string,mixed> Continuous operation (switch, icon via ICON_TRUE) */
    private const LA_PRESENTATION_SWITCH = [
        'ICON_TRUE'    => 'infinity',
        'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
    ];

    /** @var array<string,mixed> Time of day (duty cycle as clock time, switch-off time); presentation has no icon */
    private const LA_PRESENTATION_TIME = [
        'DATE'         => 0,
        'PRESENTATION' => VARIABLE_PRESENTATION_DATE_TIME,
        'TIME'         => 2,
    ];

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** @var int Device Type: Single */
    private const DEVICE_ONE = 0;

    /** @var int Device Type: Multiple */
    private const DEVICE_MULTIPLE = 1;

    /** @var int Min Symcon Object ID */
    private const IPS_MIN_ID = 10000;

    /** @var string Ident of the switch-off timer */
    private const TIMER_IDENT = 'TriggerTimer';

    /** @var string Diagnosis row color for errors (red) */
    private const ROW_ERROR = '#FFC0C0';

    /** @var string Diagnosis row color for blocking conditions (yellow) */
    private const ROW_BLOCKED = '#FFFFC0';

    /** @var int Diagnosis result color for errors (red) */
    private const RESULT_ERROR_COLOR = 0xFF0000;

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Devices ...
        $this->RegisterPropertyInteger('DeviceNumber', self::DEVICE_ONE);
        $this->RegisterPropertyInteger('StateVariable', 0);
        $this->RegisterPropertyString('StateVariables', '[]');
        $this->RegisterPropertyInteger('MotionVariable', 0);

        // Time Control ...
        $this->RegisterPropertyInteger('TimeUnit', self::TIME_MINUTES);
        $this->RegisterPropertyInteger('Duration', 10);
        $this->RegisterPropertyString('Time', '{"hour":0,"minute":1,"second":0}');
        $this->RegisterPropertyInteger('EventVariable', 0);

        // Advanced Settings ...
        $this->RegisterPropertyInteger('ScriptVariable', 0);
        $this->RegisterPropertyBoolean('OnlyScript', false);
        $this->RegisterPropertyBoolean('CheckSchedule', true);
        $this->RegisterPropertyBoolean('CheckDuration', true);
        $this->RegisterPropertyBoolean('CheckPermanent', true);
        $this->RegisterPropertyBoolean('CheckSwitchOff', false);
        $this->RegisterPropertyBoolean('SettingsDirect', false);

        // Tracks the configuration (unit, duration, time) duty_cycle was last initialized with,
        // so ApplyChanges only overwrites a value set via visualization after a configuration change.
        $this->RegisterAttributeString('LastDutyCycle', '');

        // Timer
        $this->RegisterTimer(self::TIMER_IDENT, 0, 'IPS_RequestAction(' . $this->InstanceID . ', "delay_trigger", "");');

        // Wait for the kernel before accessing foreign variables
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
    }

    /**
     * Migrates the configuration of older versions.
     *
     * Up to v7 switch variables without an action were always set directly.
     * Keep this behaviour for existing instances which rely on it.
     *
     * @param string $json Configuration data as JSON
     *
     * @return string Migrated configuration data as JSON
     */
    public function Migrate(string $json): string
    {
        //Never delete this line!
        parent::Migrate($json);

        $data = json_decode($json);
        if (!is_object($data) || !isset($data->configuration) || !is_object($data->configuration)) {
            return $json;
        }
        $config = $data->configuration;
        if (isset($config->SettingsDirect)) {
            return $json;
        }

        $variables = [];
        if ((int) ($config->DeviceNumber ?? self::DEVICE_ONE) === self::DEVICE_ONE) {
            $variables[] = (int) ($config->StateVariable ?? 0);
        } else {
            $list = json_decode((string) ($config->StateVariables ?? '[]'), true);
            foreach (is_array($list) ? $list : [] as $line) {
                $variables[] = (int) ($line['VariableID'] ?? 0);
            }
        }
        foreach ($variables as $vid) {
            if (($vid >= self::IPS_MIN_ID) && IPS_VariableExists($vid) && !HasAction($vid)) {
                $config->SettingsDirect = true;
                return json_encode($data);
            }
        }
        return $json;
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

        // Maintain variables
        $permanent = $this->ReadPropertyBoolean('CheckPermanent');
        $created = (@$this->GetIDForIdent('continuous_operation') === false);
        $this->MaintainVariable('continuous_operation', $this->Translate('Continuous operation'), VARIABLETYPE_BOOLEAN, self::LA_PRESENTATION_SWITCH, 0, $permanent);
        if ($permanent) {
            if ($created) {
                $this->SetValueBoolean('continuous_operation', false);
            }
            $this->EnableAction('continuous_operation');
        }

        // Detect a change of time unit, duration or time via attribute (works reliably across the
        // structurally different slider / date-time presentations)
        $duration = $this->ReadPropertyBoolean('CheckDuration');
        $unit = $this->ReadPropertyInteger('TimeUnit');
        $default = json_encode([$unit, $this->ReadPropertyInteger('Duration'), $this->ReadPropertyString('Time')]);
        $defaultChanged = ($this->ReadAttributeString('LastDutyCycle') !== $default);
        $created = (@$this->GetIDForIdent('duty_cycle') === false);
        if ($unit < self::TIME_CLOCK) {
            $presentation = $this->TranslatePresentation([
                'ICON'         => 'clock',
                'MAX'          => self::TIME_UNIT[$unit]['max'],
                'MIN'          => self::TIME_UNIT[$unit]['min'],
                'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                'STEP_SIZE'    => 1,
                'SUFFIX'       => self::TIME_UNIT[$unit]['suffix'],
            ]);
        } else {
            $presentation = self::LA_PRESENTATION_TIME;
        }
        $this->MaintainVariable('duty_cycle', $this->Translate('Duty cycle'), VARIABLETYPE_INTEGER, $presentation, 1, $duration);
        if ($duration) {
            // Date/time presentation has no icon parameter, so set the object icon once on creation
            if ($created) {
                IPS_SetIcon($this->GetIDForIdent('duty_cycle'), 'clock');
            }
            if ($created || $defaultChanged) {
                if ($unit < self::TIME_CLOCK) {
                    $this->SetValueInteger('duty_cycle', $this->ReadPropertyInteger('Duration'));
                } else {
                    // Clock time as local timestamp on 02.01.1970 (date presentation shows only the time)
                    $time = json_decode($this->ReadPropertyString('Time'), true);
                    $this->SetValueInteger('duty_cycle', (int) mktime((int) $time['hour'], (int) $time['minute'], (int) $time['second'], 1, 2, 1970));
                }
            }
            $this->EnableAction('duty_cycle');
        }
        $this->WriteAttributeString('LastDutyCycle', $default);

        $switchOff = $this->ReadPropertyBoolean('CheckSwitchOff');
        $created = (@$this->GetIDForIdent('switch_off_time') === false);
        $this->MaintainVariable('switch_off_time', $this->Translate('Switch-off time'), VARIABLETYPE_INTEGER, self::LA_PRESENTATION_TIME, 2, $switchOff);
        if ($switchOff && $created) {
            IPS_SetIcon($this->GetIDForIdent('switch_off_time'), 'timer');
        }

        $this->LogDebug(__FUNCTION__, 'Duration variable: ' . ($duration ? 'true' : 'false') . ', permanent variable: ' . ($permanent ? 'true' : 'false'));

        // Delete all variable registrations in order to readd them
        foreach ($this->GetMessageList() as $sender => $messages) {
            if (in_array(VM_UPDATE, $messages, true)) {
                $this->UnregisterMessage($sender, VM_UPDATE);
            }
        }

        // Foreign objects may not be available yet, ApplyChanges is called again on IPS_KERNELSTARTED
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        // Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $reference) {
            $this->UnregisterReference($reference);
        }

        // Register references
        $variables = $this->GetStateVariables();
        $objects = array_merge($variables, [
            $this->ReadPropertyInteger('MotionVariable'),
            $this->ReadPropertyInteger('EventVariable'),
            $this->ReadPropertyInteger('ScriptVariable'),
        ]);
        foreach ($objects as $object) {
            if (($object >= self::IPS_MIN_ID) && IPS_ObjectExists($object)) {
                $this->RegisterReference($object);
            }
        }

        // Check configuration
        $status = $this->CheckConfiguration($variables);
        if ($status !== IS_ACTIVE) {
            $this->StopTimer();
            $this->SetStatus($status);
            return;
        }

        // Register messages update = create our trigger
        foreach ($variables as $variable) {
            $this->RegisterMessage($variable, VM_UPDATE);
        }
        $motion = $this->ReadPropertyInteger('MotionVariable');
        if ($motion >= self::IPS_MIN_ID) {
            $this->RegisterMessage($motion, VM_UPDATE);
        }

        $this->SetStatus(IS_ACTIVE);
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
        // Get form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Extract version
        $ins = IPS_GetInstance($this->InstanceID);
        $mod = IPS_GetModule($ins['ModuleInfo']['ModuleID']);
        $lib = IPS_GetLibrary($mod['LibraryID']);
        $version = sprintf('v%s.%d', $lib['Version'], $lib['Build']);
        $this->ModifyFormElement($form['actions'], 'Version', function (array &$element) use ($version): void
        {
            $element['caption'] = $version;
        });

        // Number of devices
        $devices = $this->ReadPropertyInteger('DeviceNumber');
        $this->ModifyFormElement($form['elements'], 'StateVariable', function (array &$element) use ($devices): void
        {
            $element['visible'] = ($devices === self::DEVICE_ONE);
        });

        // Device list (set status column)
        $values = [];
        $list = json_decode($this->ReadPropertyString('StateVariables'), true);
        foreach (is_array($list) ? $list : [] as $line) {
            $values[] = ['Status' => $this->GetVariableStatus((int) ($line['VariableID'] ?? 0))];
        }
        $this->ModifyFormElement($form['elements'], 'StateVariables', function (array &$element) use ($devices, $values): void
        {
            $element['visible'] = ($devices === self::DEVICE_MULTIPLE);
            $element['values'] = $values;
        });

        // Duration inputs depending on the time unit
        $unit = $this->ReadPropertyInteger('TimeUnit');
        if ($unit < self::TIME_CLOCK) {
            $this->ModifyFormElement($form['elements'], 'Duration', function (array &$element) use ($unit): void
            {
                $element['minimum'] = self::TIME_UNIT[$unit]['min'];
                $element['maximum'] = self::TIME_UNIT[$unit]['max'];
                $element['suffix'] = $this->Translate((string) self::TIME_UNIT[$unit]['suffix']);
                $element['visible'] = true;
            });
        } else {
            $this->ModifyFormElement($form['elements'], 'Time', function (array &$element): void
            {
                $element['visible'] = true;
            });
        }

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
        switch ($message) {
            case IPS_KERNELSTARTED:
                $this->ApplyChanges();
                break;
            case VM_UPDATE:
                if (!$data[1]) {
                    break;
                }
                if ($sender === $this->ReadPropertyInteger('MotionVariable')) {
                    $this->OnMotion((bool) $data[0]);
                    break;
                }
                if (!in_array($sender, $this->GetStateVariables(), true)) {
                    $this->LogDebug(__FUNCTION__, 'SenderID: ' . $sender . ' unknown!');
                    break;
                }
                if ((bool) $data[0]) {
                    $this->LogDebug(__FUNCTION__, 'Variable #' . $sender . ' switched ON');
                    if (!$this->IsBlocked()) {
                        $this->StartTimer();
                    }
                } else {
                    $this->LogDebug(__FUNCTION__, 'Variable #' . $sender . ' switched OFF');
                    if (empty($this->GetSwitchedOnVariables())) {
                        $this->StopTimer();
                    }
                }
                break;
        }
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     *
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $this->Stringify($value));
        switch ($ident) {
            case 'continuous_operation':
                $this->SetValueBoolean($ident, (bool) $value);
                if ((bool) $value) {
                    $this->StopTimer();
                } elseif (($this->GetTimerInterval(self::TIMER_IDENT) === 0) && !empty($this->GetSwitchedOnVariables()) && !$this->IsBlocked()) {
                    $this->StartTimer();
                }
                break;
            case 'duty_cycle':
                $this->SetValueInteger($ident, (int) $value);
                break;
            case 'circuit_diagram':
                $this->Schedule((int) $value);
                break;
            case 'delay_trigger':
                $this->Trigger();
                break;
            case 'OnTimeUnit':
                $this->OnTimeUnit((string) $value);
                break;
            case 'OnCreateSchedule':
                $this->OnCreateSchedule();
                break;
            case 'OnDeviceNumber':
                $this->OnDeviceNumber((int) $value);
                break;
            case 'OnCheckState':
                $this->OnCheckState();
                break;
            default:
                $this->LogDebug(__FUNCTION__, 'Unknown ident: ' . $ident);
        }
    }

    /**
     * User has selected a new time unit.
     *
     * @param string $value JSON encoded unit and value of duration.
     *
     * @return void
     */
    private function OnTimeUnit(string $value): void
    {
        $data = json_decode($value, true);
        if (!is_array($data)) {
            return;
        }
        $unit = (int) ($data['unit'] ?? self::TIME_MINUTES);

        if ($unit < self::TIME_CLOCK) {
            $min = self::TIME_UNIT[$unit]['min'];
            $max = self::TIME_UNIT[$unit]['max'];
            $this->UpdateFormField('Duration', 'minimum', $min);
            $this->UpdateFormField('Duration', 'maximum', $max);
            $this->UpdateFormField('Duration', 'suffix', $this->Translate((string) self::TIME_UNIT[$unit]['suffix']));

            $duration = (int) ($data['value'] ?? 0);
            if (($duration < $min) || ($duration > $max)) {
                $this->UpdateFormField('Duration', 'value', 10);
            }
        }

        $this->UpdateFormField('Duration', 'visible', ($unit !== self::TIME_CLOCK));
        $this->UpdateFormField('Time', 'visible', ($unit === self::TIME_CLOCK));
    }

    /**
     * Creates a weekly schedule below the instance.
     *
     * @return void
     */
    private function OnCreateSchedule(): void
    {
        $eid = $this->CreateWeeklySchedule($this->InstanceID, self::SCHEDULE_NAME, self::SCHEDULE_IDENT, self::SCHEDULE_SWITCH, -1);
        if (IPS_EventExists($eid)) {
            $this->UpdateFormField('EventVariable', 'value', $eid);
        }
    }

    /**
     * User has selected a new number of devices.
     *
     * @param int $value Selected value.
     *
     * @return void
     */
    private function OnDeviceNumber(int $value): void
    {
        $this->UpdateFormField('StateVariable', 'visible', ($value === self::DEVICE_ONE));
        $this->UpdateFormField('StateVariables', 'visible', ($value === self::DEVICE_MULTIPLE));
    }

    /**
     * Diagnosis: shows all conditions and what the automat would do right now (read-only, switches nothing).
     *
     * @return void
     */
    private function OnCheckState(): void
    {
        $yes = $this->Translate('yes');
        $no = $this->Translate('no');
        $unused = $this->Translate('not used');
        $rows = [];

        // Configuration (errors are marked in the detail rows)
        $variables = $this->GetStateVariables();
        $status = $this->CheckConfiguration($variables);

        // Devices
        if (empty($variables)) {
            $rows[] = $this->DiagnosisRow('Device', $this->Translate('none selected'), self::ROW_ERROR);
        }
        foreach ($variables as $vid) {
            if (!IPS_VariableExists($vid)) {
                $rows[] = $this->DiagnosisRow('Device', '#' . $vid . ' ' . $this->Translate('Missing'), self::ROW_ERROR);
                continue;
            }
            $state = $this->GetVariableStatus($vid);
            if ($state === 'OK') {
                $rows[] = $this->DiagnosisRow('Device', IPS_GetName($vid) . ': ' . (GetValueBoolean($vid) ? $this->Translate('ON') : $this->Translate('OFF')));
            } else {
                $rows[] = $this->DiagnosisRow('Device', IPS_GetName($vid) . ': ' . $state, self::ROW_ERROR);
            }
        }

        // Duty cycle and timer
        $source = ($this->ReadPropertyBoolean('CheckDuration') && (@$this->GetIDForIdent('duty_cycle') !== false)) ? 'from variable' : 'from configuration';
        $interval = $this->CalculateTimer();
        $rows[] = $this->DiagnosisRow('Duty cycle', $this->FormatDuration($interval) . ' (' . $this->Translate($source) . ')', ($interval > 0) ? '' : self::ROW_ERROR);
        $running = ($this->GetTimerInterval(self::TIMER_IDENT) > 0);
        $switchOff = (int) $this->GetBuffer('SwitchOff');
        if ($running) {
            $timer = ($switchOff > 0) ? sprintf($this->Translate('running, switch-off at %s'), date('H:i:s', $switchOff)) : $this->Translate('running');
        } else {
            $timer = $this->Translate('not running');
        }
        $rows[] = $this->DiagnosisRow('Timer', $timer);

        // Continuous operation
        if ($this->ReadPropertyBoolean('CheckPermanent')) {
            $continuous = $this->IsContinuous();
            $rows[] = $this->DiagnosisRow('Continuous operation', $continuous ? $this->Translate('ON') : $this->Translate('OFF'), $continuous ? self::ROW_BLOCKED : '');
        } else {
            $rows[] = $this->DiagnosisRow('Continuous operation', $unused);
        }

        // Weekly schedule
        $eid = $this->ReadPropertyInteger('EventVariable');
        if ($eid < self::IPS_MIN_ID) {
            $rows[] = $this->DiagnosisRow('Weekly schedule', $unused);
        } elseif (!IPS_EventExists($eid)) {
            $rows[] = $this->DiagnosisRow('Weekly schedule', $this->Translate('Missing'), self::ROW_ERROR);
        } else {
            $info = $this->GetWeeklyScheduleInfo($eid);
            $color = '';
            if ($info['WeekPlanActiv'] !== 1) {
                $schedule = $this->Translate('disabled (ignored)');
            } elseif ($info['ActionID'] === self::SCHEDULE_OFF) {
                $schedule = $this->Translate('Inactive');
                $color = self::ROW_BLOCKED;
            } else {
                $schedule = $this->Translate('Active');
            }
            $schedule .= ' (' . $this->Translate('start on activation') . ': ' . ($this->ReadPropertyBoolean('CheckSchedule') ? $yes : $no) . ')';
            $rows[] = $this->DiagnosisRow('Weekly schedule', $schedule, $color);
        }

        // Motion detector
        $mid = $this->ReadPropertyInteger('MotionVariable');
        $motion = false;
        if ($mid < self::IPS_MIN_ID) {
            $rows[] = $this->DiagnosisRow('Motion detector', $unused);
        } elseif (!IPS_VariableExists($mid)) {
            $rows[] = $this->DiagnosisRow('Motion detector', $this->Translate('Missing'), self::ROW_ERROR);
        } else {
            $motion = (bool) GetValue($mid);
            $rows[] = $this->DiagnosisRow('Motion detector', $motion ? $this->Translate('motion detected') : $this->Translate('no motion'), $motion ? self::ROW_BLOCKED : '');
        }

        // Script
        $onlyScript = $this->ReadPropertyBoolean('OnlyScript');
        $sid = $this->ReadPropertyInteger('ScriptVariable');
        if ($sid < self::IPS_MIN_ID) {
            if ($onlyScript) {
                $rows[] = $this->DiagnosisRow('Script', $this->Translate('none, but only the script should be executed!'), self::ROW_ERROR);
            } else {
                $rows[] = $this->DiagnosisRow('Script', $this->Translate('none'));
            }
        } elseif (!IPS_ScriptExists($sid)) {
            $rows[] = $this->DiagnosisRow('Script', $this->Translate('Missing'), self::ROW_ERROR);
        } else {
            $rows[] = $this->DiagnosisRow('Script', sprintf($this->Translate($onlyScript ? '%s (instead of switching off)' : '%s (in addition to switching off)'), IPS_GetName($sid)));
        }

        // Result
        $error = false;
        $reason = $this->GetBlockReason();
        if ($status !== IS_ACTIVE) {
            $result = $this->Translate('The automat does not work, see the marked entries!');
            $error = true;
        } elseif ($interval <= 0) {
            $result = $this->Translate('No duty cycle is started, because the duty cycle is 0!');
            $error = true;
        } elseif ($running) {
            $time = ($switchOff > 0) ? date('H:i:s', $switchOff) : '?';
            $result = sprintf($this->Translate($onlyScript ? 'The script is executed at %s.' : 'The devices are switched off at %s.'), $time);
            if ($motion) {
                $result .= ' ' . $this->Translate('Motion detected, the duty cycle is restarted when it expires.');
            }
        } elseif ($reason !== '') {
            $result = sprintf($this->Translate('No duty cycle is started: %s'), $this->Translate($reason));
        } elseif (!empty($this->GetSwitchedOnVariables())) {
            $result = $this->Translate('A device is switched on, but no duty cycle is running.');
        } else {
            $result = $this->Translate('Switching on a device starts the duty cycle.');
        }

        $this->UpdateFormField('DiagnosisList', 'rowCount', count($rows));
        $this->UpdateFormField('DiagnosisList', 'values', json_encode($rows));
        $this->UpdateFormField('DiagnosisResult', 'caption', $this->Translate('Result') . ': ' . $result);
        $this->UpdateFormField('DiagnosisResult', 'color', $error ? self::RESULT_ERROR_COLOR : 0);
        $this->UpdateFormField('DiagnosisPopup', 'visible', true);
    }

    /**
     * Builds a row of the diagnosis list.
     *
     * @param string $check Name of the check (untranslated)
     * @param string $state State (already translated)
     * @param string $color Row color (empty for transparent)
     *
     * @return array<string,string> List row
     */
    private function DiagnosisRow(string $check, string $state, string $color = ''): array
    {
        return ['Check' => $this->Translate($check), 'State' => $state, 'rowColor' => $color];
    }

    /**
     * Formats a duration as HH:MM:SS.
     *
     * @param int $interval Duration in milliseconds
     *
     * @return string Formatted duration
     */
    private function FormatDuration(int $interval): string
    {
        $seconds = intdiv($interval, 1000);
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * Motion detector has changed its state.
     *
     * @param bool $motion New state of the motion detector.
     *
     * @return void
     */
    private function OnMotion(bool $motion): void
    {
        $this->LogDebug(__FUNCTION__, 'Motion: ' . ($motion ? 'true' : 'false'));
        // Restart the duty cycle when the motion has ended
        if (!$motion && ($this->GetTimerInterval(self::TIMER_IDENT) > 0)) {
            $this->StartTimer();
        }
    }

    /**
     * Duty cycle has expired, switch off the devices and/or execute the script.
     *
     * @return void
     */
    private function Trigger(): void
    {
        $devices = $this->GetSwitchedOnVariables();
        if (empty($devices)) {
            $this->LogDebug(__FUNCTION__, 'All devices already OFF - stop timer!');
            $this->StopTimer();
            return;
        }
        if ($this->IsContinuous()) {
            $this->LogDebug(__FUNCTION__, 'Continuous operation is ON - stop timer!');
            $this->StopTimer();
            return;
        }
        $motion = $this->ReadPropertyInteger('MotionVariable');
        if (($motion >= self::IPS_MIN_ID) && IPS_VariableExists($motion) && (bool) GetValue($motion)) {
            $this->LogDebug(__FUNCTION__, 'Motion detection active - restart duty cycle!');
            $this->StartTimer();
            return;
        }
        if (!$this->ReadPropertyBoolean('OnlyScript')) {
            foreach ($devices as $vid) {
                $this->SwitchOff($vid);
            }
        }
        $script = $this->ReadPropertyInteger('ScriptVariable');
        if ($script >= self::IPS_MIN_ID) {
            if (IPS_ScriptExists($script)) {
                $ret = IPS_RunScriptEx($script, ['VARIABLE' => $devices[0], 'INSTANCE' => $this->InstanceID]);
                $this->LogDebug(__FUNCTION__, 'Script execute return value: ' . $this->Stringify($ret));
            } else {
                $this->LogDebug(__FUNCTION__, 'Script #' . $script . ' does not exist!');
            }
        }
        $this->StopTimer();
    }

    /**
     * Weekly schedule has switched.
     *
     * @param int $value Action value (ON=1, OFF=2)
     *
     * @return void
     */
    private function Schedule(int $value): void
    {
        $this->LogDebug(__FUNCTION__, 'Value: ' . $value);
        if (!$this->ReadPropertyBoolean('CheckSchedule') || ($value !== self::SCHEDULE_ON)) {
            return;
        }
        if ($this->IsContinuous() || empty($this->GetSwitchedOnVariables())) {
            return;
        }
        if ($this->GetTimerInterval(self::TIMER_IDENT) === 0) {
            $this->LogDebug(__FUNCTION__, 'State is ON and no timer active - start duty cycle!');
            $this->StartTimer();
        }
    }

    /**
     * Starts (or restarts) the duty cycle timer.
     *
     * @return int Timer interval in milliseconds (0 if no valid duty cycle is set)
     */
    private function StartTimer(): int
    {
        $interval = $this->CalculateTimer();
        $this->LogDebug(__FUNCTION__, 'Interval: ' . $interval . 'ms');
        if ($interval <= 0) {
            $this->StopTimer();
            return 0;
        }
        $this->SetTimerInterval(self::TIMER_IDENT, $interval);
        $this->UpdateSwitchOffTime(time() + intdiv($interval, 1000));
        return $interval;
    }

    /**
     * Stops the duty cycle timer.
     *
     * @return void
     */
    private function StopTimer(): void
    {
        $this->SetTimerInterval(self::TIMER_IDENT, 0);
        $this->UpdateSwitchOffTime(0);
    }

    /**
     * Remembers the switch-off time (buffer for the diagnosis) and updates the variable (if created).
     *
     * @param int $timestamp Switch-off time or 0 if no timer is active.
     *
     * @return void
     */
    private function UpdateSwitchOffTime(int $timestamp): void
    {
        $this->SetBuffer('SwitchOff', (string) $timestamp);
        if ($this->ReadPropertyBoolean('CheckSwitchOff') && (@$this->GetIDForIdent('switch_off_time') !== false)) {
            $this->SetValueInteger('switch_off_time', $timestamp);
        }
    }

    /**
     * Switches off a single device.
     *
     * @param int $vid Variable ID of the device.
     *
     * @return bool True if the device was switched off.
     */
    private function SwitchOff(int $vid): bool
    {
        if (HasAction($vid)) {
            $ok = (@RequestAction($vid, false) !== false);
        } elseif ($this->IsDirectSwitchable($vid)) {
            $ok = @SetValueBoolean($vid, false);
        } else {
            $ok = false;
        }
        if ($ok) {
            $this->LogDebug(__FUNCTION__, 'Variable #' . $vid . ' switched OFF');
        } else {
            $this->LogDebug(__FUNCTION__, 'Variable #' . $vid . ' could not be switched!');
            $this->LogMessage(sprintf($this->Translate('Device #%d could not be switched off!'), $vid), KL_WARNING);
        }
        return $ok;
    }

    /**
     * Checks whether continuous operation is active.
     *
     * @return bool True if continuous operation is active.
     */
    private function IsContinuous(): bool
    {
        return $this->ReadPropertyBoolean('CheckPermanent') && (@$this->GetIDForIdent('continuous_operation') !== false) && (bool) $this->GetValue('continuous_operation');
    }

    /**
     * Checks whether the automat is blocked by continuous operation or the weekly schedule.
     *
     * @return bool True if no duty cycle should be started.
     */
    private function IsBlocked(): bool
    {
        $reason = $this->GetBlockReason();
        if ($reason !== '') {
            $this->LogDebug(__FUNCTION__, $reason);
            return true;
        }
        return false;
    }

    /**
     * Returns the reason why the automat is blocked.
     *
     * @return string Untranslated reason or empty string if not blocked.
     */
    private function GetBlockReason(): string
    {
        if ($this->IsContinuous()) {
            return 'Continuous operation is active!';
        }
        $eid = $this->ReadPropertyInteger('EventVariable');
        if (($eid >= self::IPS_MIN_ID) && IPS_EventExists($eid)) {
            $state = $this->GetWeeklyScheduleInfo($eid);
            if (($state['WeekPlanActiv'] === 1) && ($state['ActionID'] === self::SCHEDULE_OFF)) {
                return 'Weekly schedule is inactive!';
            }
        }
        return '';
    }

    /**
     * Calculate duration timer.
     *
     * @return int Timer interval in milliseconds
     */
    private function CalculateTimer(): int
    {
        $unit = $this->ReadPropertyInteger('TimeUnit');
        $useVariable = $this->ReadPropertyBoolean('CheckDuration') && (@$this->GetIDForIdent('duty_cycle') !== false);

        if ($unit < self::TIME_CLOCK) {
            $time = $useVariable ? (int) $this->GetValue('duty_cycle') : $this->ReadPropertyInteger('Duration');
            return 1000 * (int) self::TIME_UNIT[$unit]['factor'] * $time;
        }
        if ($useVariable) {
            // Only the local time of day of the timestamp is relevant
            $ts = (int) $this->GetValue('duty_cycle');
            return 1000 * ((int) date('G', $ts) * 3600 + (int) date('i', $ts) * 60 + (int) date('s', $ts));
        }
        $time = json_decode($this->ReadPropertyString('Time'), true);
        return 1000 * ((int) $time['hour'] * 3600 + (int) $time['minute'] * 60 + (int) $time['second']);
    }

    /**
     * Returns the configured switch variables (single or multiple devices).
     *
     * @return array<int> Variable IDs
     */
    private function GetStateVariables(): array
    {
        if ($this->ReadPropertyInteger('DeviceNumber') === self::DEVICE_ONE) {
            $variable = $this->ReadPropertyInteger('StateVariable');
            return ($variable >= self::IPS_MIN_ID) ? [$variable] : [];
        }
        $variables = [];
        $list = json_decode($this->ReadPropertyString('StateVariables'), true);
        foreach (is_array($list) ? $list : [] as $line) {
            $variable = (int) ($line['VariableID'] ?? 0);
            if ($variable >= self::IPS_MIN_ID) {
                $variables[] = $variable;
            }
        }
        return array_values(array_unique($variables));
    }

    /**
     * Returns all configured switch variables which are currently switched on.
     *
     * @return array<int> Variable IDs
     */
    private function GetSwitchedOnVariables(): array
    {
        $variables = [];
        foreach ($this->GetStateVariables() as $vid) {
            if (IPS_VariableExists($vid) && (IPS_GetVariable($vid)['VariableType'] === VARIABLETYPE_BOOLEAN) && GetValueBoolean($vid)) {
                $variables[] = $vid;
            }
        }
        return $variables;
    }

    /**
     * Checks the configuration and returns the resulting instance status.
     *
     * @param array<int> $variables Configured switch variables.
     *
     * @return int Status code
     */
    private function CheckConfiguration(array $variables): int
    {
        if (empty($variables)) {
            $this->LogDebug(__FUNCTION__, 'No switch variable configured!');
            return IS_INACTIVE;
        }
        foreach ($variables as $vid) {
            if (!IPS_VariableExists($vid)) {
                $this->LogDebug(__FUNCTION__, 'Variable #' . $vid . ' does not exist!');
                return self::STATUS_VARIABLE_MISSING;
            }
            if ($this->GetVariableStatus($vid) !== 'OK') {
                $this->LogDebug(__FUNCTION__, 'Variable #' . $vid . ' is not switchable!');
                return self::STATUS_VARIABLE_INVALID;
            }
        }
        $motion = $this->ReadPropertyInteger('MotionVariable');
        if (($motion >= self::IPS_MIN_ID) && !IPS_VariableExists($motion)) {
            $this->LogDebug(__FUNCTION__, 'Motion variable #' . $motion . ' does not exist!');
            return self::STATUS_MOTION_MISSING;
        }
        $event = $this->ReadPropertyInteger('EventVariable');
        if (($event >= self::IPS_MIN_ID) && !IPS_EventExists($event)) {
            $this->LogDebug(__FUNCTION__, 'Event #' . $event . ' does not exist!');
            return self::STATUS_SCHEDULE_MISSING;
        }
        $script = $this->ReadPropertyInteger('ScriptVariable');
        if (($script >= self::IPS_MIN_ID) && !IPS_ScriptExists($script)) {
            $this->LogDebug(__FUNCTION__, 'Script #' . $script . ' does not exist!');
            return self::STATUS_SCRIPT_MISSING;
        }
        if ($this->ReadPropertyBoolean('OnlyScript') && ($script < self::IPS_MIN_ID)) {
            $this->LogDebug(__FUNCTION__, 'Only script is set, but no script is selected!');
            return self::STATUS_SCRIPT_REQUIRED;
        }
        return IS_ACTIVE;
    }

    /**
     * Checks whether a variable without action may be set directly.
     * Only allowed by the user and only for variables which do not belong to an instance.
     *
     * @param int $vid Variable ID
     *
     * @return bool True if the value may be set directly
     */
    private function IsDirectSwitchable(int $vid): bool
    {
        return $this->ReadPropertyBoolean('SettingsDirect') && !IPS_InstanceExists(IPS_GetParent($vid));
    }

    /**
     * Returns the status of a given switch variable.
     *
     * @param int $vid Variable ID.
     *
     * @return string 'OK' or a translated status message
     */
    private function GetVariableStatus(int $vid): string
    {
        if (!IPS_VariableExists($vid)) {
            return $this->Translate('Missing');
        }
        if (IPS_GetVariable($vid)['VariableType'] !== VARIABLETYPE_BOOLEAN) {
            return $this->Translate('Bool required');
        }
        if (!HasAction($vid) && !$this->IsDirectSwitchable($vid)) {
            return $this->Translate('Action required');
        }
        return 'OK';
    }
}
