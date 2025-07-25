<?php

declare(strict_types=1);

namespace JuControlDevice\Services;

use JuControlDevice\Config\DeviceConstants;

class VariableManager
{
    private $module;
    private string $deviceType;

    public function __construct($module, string $deviceType)
    {
        $this->module = $module;
        $this->deviceType = $deviceType;
    }

    public function registerAllVariables(): void
    {
        $this->registerProfiles();
        $this->registerCommonVariables();
        
        if ($this->deviceType === DeviceConstants::DEVICE_TYPE_I_SOFT_SAFE_PLUS) {
            $this->registerISoftSafePlusVariables();
        }
    }

    private function registerProfiles(): void
    {
        $profiles = [
            'JCD.lph' => ['type' => 'integer', 'icon' => 'Drops', 'suffix' => ' l/h', 'min' => 0, 'max' => 0, 'step' => 0],
            'JCD.dH_int' => ['type' => 'integer', 'icon' => 'Drops', 'suffix' => ' °dH', 'min' => 0, 'max' => 50, 'step' => 1],
            'JCD.dH_float' => ['type' => 'float', 'icon' => 'Drops', 'suffix' => ' °dH', 'min' => 0, 'max' => 50, 'step' => 0.1],
            'JCD.Days' => ['type' => 'integer', 'icon' => 'Clock', 'suffix' => ' days', 'min' => 0, 'max' => 0, 'step' => 0],
            'JCD.kg' => ['type' => 'integer', 'icon' => '', 'suffix' => ' kg', 'min' => 0, 'max' => 0, 'step' => 0],
            'JCD.Liter' => ['type' => 'integer', 'icon' => 'Wave', 'suffix' => ' liters', 'min' => 0, 'max' => 99999999, 'step' => 1],
            'JCD.Minutes.WSMaxPeriodOfUse' => ['type' => 'integer', 'icon' => 'Clock', 'suffix' => ' minutes', 'min' => 0, 'max' => 600, 'step' => 10],
            'JCD.Liters.WSMaxQuantity' => ['type' => 'integer', 'icon' => '', 'suffix' => ' l', 'min' => 0, 'max' => 3000, 'step' => 100],
            'JCD.lph.WSMaxWaterFlow' => ['type' => 'integer', 'icon' => '', 'suffix' => ' l/h', 'min' => 0, 'max' => 5000, 'step' => 100],
        ];

        foreach ($profiles as $name => $config) {
            $this->registerProfile($name, $config);
        }

        $this->registerWaterSceneProfile();
    }

    private function registerProfile(string $name, array $config): void
    {
        if (!IPS_VariableProfileExists($name)) {
            $type = match ($config['type']) {
                'integer' => VARIABLETYPE_INTEGER,
                'float' => VARIABLETYPE_FLOAT,
                'boolean' => VARIABLETYPE_BOOLEAN,
                default => VARIABLETYPE_STRING,
            };
            
            IPS_CreateVariableProfile($name, $type);
        }

        IPS_SetVariableProfileIcon($name, $config['icon']);
        IPS_SetVariableProfileText($name, '', $config['suffix']);
        
        if (isset($config['min'], $config['max'], $config['step'])) {
            IPS_SetVariableProfileValues($name, $config['min'], $config['max'], $config['step']);
        }
    }

    private function registerWaterSceneProfile(): void
    {
        $name = 'JCD.Waterscene';
        
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, VARIABLETYPE_INTEGER);
        }

        IPS_SetVariableProfileIcon($name, 'Drops');
        IPS_SetVariableProfileText($name, '', '');
        IPS_SetVariableProfileValues($name, 0, 4, 0);

        $associations = [
            [0, $this->translate('Normal mode'), 'Ok', 0x00FF00],
            [1, $this->translate('Shower'), 'Shower', 0xFF9C00],
            [2, $this->translate('Filling of heating'), 'Temperature', 0xFF9C00],
            [3, $this->translate('Garden irrigation'), 'Drops', 0xFF9C00],
            [4, $this->translate('Washing'), 'Pants', 0xFF9C00],
        ];

        foreach ($associations as $association) {
            IPS_SetVariableProfileAssociation($name, ...$association);
        }
    }

    private function registerCommonVariables(): void
    {
        $position = 0;
        $variables = [
            [DeviceConstants::VAR_DEVICE_STATE, 'State', '', VARIABLETYPE_STRING],
            [DeviceConstants::VAR_INPUT_HARDNESS, 'Input water hardness', 'JCD.dH_float', VARIABLETYPE_FLOAT],
            [DeviceConstants::VAR_TARGET_HARDNESS, 'Desired water hardness', 'JCD.dH_int', VARIABLETYPE_INTEGER],
            [DeviceConstants::VAR_SALT_LEVEL, 'Salt storage', 'JCD.kg', VARIABLETYPE_INTEGER],
            [DeviceConstants::VAR_SALT_PERCENT, 'Fill level salt', '~Intensity.100', VARIABLETYPE_INTEGER],
            [DeviceConstants::VAR_SALT_DAYS, 'Range salt storage', 'JCD.Days', VARIABLETYPE_INTEGER],
            [DeviceConstants::VAR_CURRENT_FLOW, 'Water flow', 'JCD.lph', VARIABLETYPE_INTEGER],
            [DeviceConstants::VAR_WATER_STOP, 'Water stop', '~Switch', VARIABLETYPE_BOOLEAN],
            [DeviceConstants::VAR_REGENERATION, 'Regeneration', '~Switch', VARIABLETYPE_BOOLEAN],
            [DeviceConstants::VAR_ACTIVE_SCENE, 'Active water scene', 'JCD.Waterscene', VARIABLETYPE_INTEGER],
        ];

        foreach ($variables as $variable) {
            [$ident, $name, $profile, $type] = $variable;
            $this->module->RegisterVariableByType($type, $ident, $this->translate($name), $profile, ++$position);
            
            if (in_array($ident, [DeviceConstants::VAR_WATER_STOP, DeviceConstants::VAR_REGENERATION, DeviceConstants::VAR_ACTIVE_SCENE])) {
                $this->module->EnableAction($ident);
            }
        }
    }

    private function registerISoftSafePlusVariables(): void
    {
        $position = 20; // Start after common variables
        
        $profiles = [
            'JCD.Minutes' => ['type' => 'integer', 'icon' => 'Clock', 'suffix' => ' minutes', 'min' => 0, 'max' => 0, 'step' => 0],
            'JCD.Hours' => ['type' => 'integer', 'icon' => 'Clock', 'suffix' => ' hours', 'min' => 0, 'max' => 10, 'step' => 1],
            'JCD.WSHolidayMode' => ['type' => 'integer', 'icon' => '', 'suffix' => '', 'min' => 0, 'max' => 3, 'step' => 0],
            'JCD.NoYes' => ['type' => 'boolean', 'icon' => '', 'suffix' => '', 'min' => 0, 'max' => 1, 'step' => 1],
        ];

        foreach ($profiles as $name => $config) {
            $this->registerProfile($name, $config);
        }

        $this->registerHolidayModeAssociations();
        $this->registerNoYesAssociations();

        $variables = [
            ['deviceID', 'Device number', '', VARIABLETYPE_STRING],
            ['wsHolidayMode', 'Holiday mode', 'JCD.WSHolidayMode', VARIABLETYPE_INTEGER],
            [DeviceConstants::VAR_HOLIDAY, 'Holiday', '~Switch', VARIABLETYPE_BOOLEAN],
            [DeviceConstants::VAR_SLEEP_MODE, 'Sleep mode', '~Switch', VARIABLETYPE_BOOLEAN],
            ['wsSleepModeDuration', 'Sleep mode duration', 'JCD.Hours', VARIABLETYPE_INTEGER],
            [DeviceConstants::VAR_BATTERY_STATE, 'Battery status', '~Intensity.100', VARIABLETYPE_INTEGER],
            [DeviceConstants::VAR_BATTERY_RUNTIME, 'Battery runtime (H:MM:SS)', '', VARIABLETYPE_STRING],
            ['ccuVersion', 'Connectivity module version', '', VARIABLETYPE_STRING],
            ['nextService', 'Next service', 'JCD.Days', VARIABLETYPE_INTEGER],
            ['hasEmergencySupply', 'Safety-Module', 'JCD.NoYes', VARIABLETYPE_BOOLEAN],
            ['totalService', 'Number of services', '', VARIABLETYPE_INTEGER],
            ['remainingTime', 'Remaining time scene', 'JCD.Minutes', VARIABLETYPE_INTEGER],
        ];

        foreach ($variables as $variable) {
            [$ident, $name, $profile, $type] = $variable;
            $this->module->RegisterVariableByType($type, $ident, $this->translate($name), $profile, ++$position);
            
            if (in_array($ident, ['wsHolidayMode', DeviceConstants::VAR_HOLIDAY, DeviceConstants::VAR_SLEEP_MODE, 'wsSleepModeDuration'])) {
                $this->module->EnableAction($ident);
            }
        }
    }

    private function registerHolidayModeAssociations(): void
    {
        $associations = [
            [0, $this->translate('no holiday mode'), '', -1],
            [1, $this->translate('Holiday mode 1'), '', -1],
            [2, $this->translate('Holiday mode 2'), '', -1],
            [3, $this->translate('Shut off water'), '', -1],
        ];

        foreach ($associations as $association) {
            IPS_SetVariableProfileAssociation('JCD.WSHolidayMode', ...$association);
        }
    }

    private function registerNoYesAssociations(): void
    {
        $associations = [
            [false, $this->translate('No'), '', -1],
            [true, $this->translate('Yes'), '', -1],
        ];

        foreach ($associations as $association) {
            IPS_SetVariableProfileAssociation('JCD.NoYes', ...$association);
        }
    }

    private function translate(string $text): string
    {
        return $this->module->Translate($text);
    }
}