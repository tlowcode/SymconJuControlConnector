<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/DebugHelper.php';

use JuControlDevice\Services\ApiClient;
use JuControlDevice\Services\DeviceFactory;
use JuControlDevice\Config\DeviceConstants;
use JuControlDevice\Exceptions\JuControlException;
use JuControlDevice\Contracts\DeviceInterface;

class JuControlDeviceModule extends IPSModule
{
    use DebugHelper;

    private ?DeviceInterface $device = null;
    private ?DeviceFactory $deviceFactory = null;

    // Properties
    private const PROP_USERNAME = 'Username';
    private const PROP_PASSWORD = 'Password';
    private const PROP_DEVICETYPE = 'DeviceType';
    private const PROP_SERIALNUMBER = 'SerialNumber';
    private const PROP_REFRESHRATE = 'RefreshRate';

    // Attributes
    private const ATTR_TOKEN_KNM = 'AccessTokenMyJudoEU';
    private const ATTR_TOKEN_JUDO = 'AccessTokenMyJudoCom';
    private const ATTR_LAST_UPDATE = 'LastUpdate';

    public function Create(): void
    {
        parent::Create();

        // Register properties
        $this->RegisterPropertyString(self::PROP_USERNAME, '');
        $this->RegisterPropertyString(self::PROP_PASSWORD, '');
        $this->RegisterPropertyString(self::PROP_DEVICETYPE, '');
        $this->RegisterPropertyString(self::PROP_SERIALNUMBER, '');
        $this->RegisterPropertyInteger(self::PROP_REFRESHRATE, DeviceConstants::DEFAULT_REFRESH_RATE);

        // Register attributes
        $this->RegisterAttributeString(self::ATTR_TOKEN_KNM, '');
        $this->RegisterAttributeString(self::ATTR_TOKEN_JUDO, '');
        $this->RegisterAttributeInteger(self::ATTR_LAST_UPDATE, 0);

        // Register timers
        $this->RegisterTimer('RefreshTimer', 0, 'JCD_RefreshData(' . $this->InstanceID . ');');
        $this->RegisterTimer('SleepTimer', 0, 'JCD_Wakeup(' . $this->InstanceID . ');');

        $this->SetStatus(IS_INACTIVE);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        try {
            $this->validateConfiguration();
            $this->initializeClients();
            $this->registerVariables();
            
            if ($this->connectToDevice()) {
                $this->startRefreshTimer();
                $this->RefreshData();
            }
        } catch (JuControlException $e) {
            $this->SetStatus($e->getCode());
            $this->LogMessage($e->getMessage(), KL_ERROR);
        } catch (\Exception $e) {
            $this->SetStatus(IS_EBASE + 1);
            $this->LogMessage('Unexpected error: ' . $e->getMessage(), KL_ERROR);
        }
    }

    public function RefreshData(): bool
    {
        try {
            if (!$this->device) {
                if (!$this->connectToDevice()) {
                    return false;
                }
            }

            if (!$this->device->isOnline()) {
                if (!$this->connectToDevice()) {
                    return false;
                }
            }

            $deviceData = $this->device->refreshData();
            $this->updateVariables($deviceData);
            $this->WriteAttributeInteger(self::ATTR_LAST_UPDATE, time());
            
            $this->SetStatus(IS_ACTIVE);
            return true;

        } catch (JuControlException $e) {
            $this->SetStatus($e->getCode());
            $this->SendDebug(__FUNCTION__, $e->getMessage(), 0);
            return false;
        } catch (\Exception $e) {
            $this->SendDebug(__FUNCTION__, 'Error: ' . $e->getMessage(), 0);
            return false;
        }
    }

    public function TestConnection(): bool
    {
        try {
            $this->initializeClients();
            return $this->connectToDevice();
        } catch (\Exception $e) {
            $this->SendDebug(__FUNCTION__, 'Connection test failed: ' . $e->getMessage(), 0);
            return false;
        }
    }

    public function RequestAction($ident, $value): void
    {
        try {
            if (!$this->device) {
                throw new \RuntimeException('Device not connected');
            }

            $command = $this->buildCommandForAction($ident, $value);
            
            if ($this->device->sendCommand($command['command'], $command['parameters'])) {
                $this->SetValue($ident, $value);
                $this->SendDebug(__FUNCTION__, "Successfully executed action {$ident} with value {$value}", 0);
            } else {
                $this->SendDebug(__FUNCTION__, "Failed to execute action {$ident}", 0);
            }

        } catch (\Exception $e) {
            $this->SendDebug(__FUNCTION__, 'RequestAction error: ' . $e->getMessage(), 0);
        }
    }

    public function Wakeup(): void
    {
        $this->SendDebug(__FUNCTION__, 'Resuming regular activity', 0);
        $this->SetTimerInterval('SleepTimer', 0);
        $this->startRefreshTimer();
    }

    private function validateConfiguration(): void
    {
        $required = [
            self::PROP_USERNAME => $this->ReadPropertyString(self::PROP_USERNAME),
            self::PROP_PASSWORD => $this->ReadPropertyString(self::PROP_PASSWORD),
            self::PROP_DEVICETYPE => $this->ReadPropertyString(self::PROP_DEVICETYPE),
        ];

        foreach ($required as $property => $value) {
            if (empty($value)) {
                throw JuControlException::informationIncomplete("Property {$property} is required");
            }
        }
    }

    private function initializeClients(): void
    {
        $knmClient = new ApiClient(DeviceConstants::SERVER_KNM);
        $judoClient = new ApiClient(DeviceConstants::SERVER_JUDO);

        $username = $this->ReadPropertyString(self::PROP_USERNAME);
        $password = $this->ReadPropertyString(self::PROP_PASSWORD);

        if (!$knmClient->authenticate($username, $password)) {
            throw JuControlException::authenticationFailed('KNM server authentication failed');
        }

        if (!$judoClient->authenticate($username, $password)) {
            throw JuControlException::authenticationFailed('Judo server authentication failed');
        }

        $this->WriteAttributeString(self::ATTR_TOKEN_KNM, $knmClient->getToken());
        $this->WriteAttributeString(self::ATTR_TOKEN_JUDO, $judoClient->getToken());

        $this->deviceFactory = new DeviceFactory($knmClient, $judoClient);
    }

    private function connectToDevice(): bool
    {
        if (!$this->deviceFactory) {
            $this->initializeClients();
        }

        $deviceType = $this->ReadPropertyString(self::PROP_DEVICETYPE);
        $serialNumber = $this->ReadPropertyString(self::PROP_SERIALNUMBER);

        $this->device = $this->deviceFactory->createDevice($deviceType, $serialNumber);
        
        return $this->device->connect();
    }

    private function registerVariables(): void
    {
        $deviceType = $this->ReadPropertyString(self::PROP_DEVICETYPE);
        $variableManager = new VariableManager($this, $deviceType);
        $variableManager->registerAllVariables();
    }

    private function updateVariables(array $deviceData): void
    {
        foreach ($deviceData as $key => $value) {
            $ident = $this->mapDataKeyToVariableIdent($key);
            if ($ident && $this->GetIDForIdent($ident)) {
                $this->updateIfNecessary($value, $ident);
            }
        }
    }

    private function mapDataKeyToVariableIdent(string $key): ?string
    {
        return match ($key) {
            'status' => DeviceConstants::VAR_DEVICE_STATE,
            'currentFlow' => DeviceConstants::VAR_CURRENT_FLOW,
            'waterStop' => DeviceConstants::VAR_WATER_STOP,
            'regeneration' => DeviceConstants::VAR_REGENERATION,
            'inputHardness' => DeviceConstants::VAR_INPUT_HARDNESS,
            'targetHardness' => DeviceConstants::VAR_TARGET_HARDNESS,
            'activeScene' => DeviceConstants::VAR_ACTIVE_SCENE,
            default => null,
        };
    }

    private function buildCommandForAction(string $ident, $value): array
    {
        return match ($ident) {
            DeviceConstants::VAR_WATER_STOP => [
                'command' => 'valve',
                'parameters' => ['group' => 'waterstop', 'parameter' => $value ? 'close' : 'open']
            ],
            DeviceConstants::VAR_REGENERATION => [
                'command' => 'regeneration',
                'parameters' => ['group' => 'device', 'parameter' => $value ? 'start' : 'stop']
            ],
            default => throw new \InvalidArgumentException("Unsupported action: {$ident}"),
        };
    }

    private function updateIfNecessary($newValue, string $ident): void
    {
        try {
            $id = $this->GetIDForIdent($ident);
            $variableType = IPS_GetVariable($id)['VariableType'];
            
            if (in_array($variableType, [VARIABLETYPE_FLOAT, VARIABLETYPE_INTEGER]) && !is_numeric($newValue)) {
                return;
            }
            
            if ($this->GetValue($ident) != $newValue) {
                $this->SetValue($ident, $newValue);
                $this->SendDebug(__FUNCTION__, "Updated {$ident} to: {$newValue}", 0);
            }
        } catch (\Exception $e) {
            $this->SendDebug(__FUNCTION__, "Failed to update {$ident}: " . $e->getMessage(), 0);
        }
    }

    private function startRefreshTimer(): void
    {
        $refreshRate = $this->ReadPropertyInteger(self::PROP_REFRESHRATE);
        $this->SetTimerInterval('RefreshTimer', $refreshRate * 1000);
    }

    private function sleep(int $milliseconds): void
    {
        $this->SendDebug(__FUNCTION__, "Sleeping for {$milliseconds}ms", 0);
        $this->SetTimerInterval('SleepTimer', $milliseconds);
        $this->SetTimerInterval('RefreshTimer', 0);
    }
}