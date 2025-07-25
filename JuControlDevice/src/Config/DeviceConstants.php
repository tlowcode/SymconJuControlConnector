<?php

declare(strict_types=1);

namespace JuControlDevice\Config;

final class DeviceConstants
{
    // Device Types
    public const DEVICE_TYPE_I_SOFT_SAFE_PLUS = '0x33';
    public const DEVICE_TYPE_I_SOFT_PLUS = 'i-soft plus';

    // API Servers
    public const SERVER_KNM = 'https://www.myjudo.eu:443/interface';
    public const SERVER_JUDO = 'https://www.my-judo.com:8124';

    // Status Codes
    public const STATUS_AUTHENTICATION_FAILED = 201;
    public const STATUS_WRONG_DEVICETYPE = 202;
    public const STATUS_DEVICE_NOT_ONLINE = 203;
    public const STATUS_DEVICE_NOT_FOUND = 204;
    public const STATUS_INFORMATION_INCOMPLETE = 205;

    // Variable Identifiers
    public const VAR_DEVICE_STATE = 'deviceState';
    public const VAR_BATTERY_STATE = 'batteryState';
    public const VAR_BATTERY_RUNTIME = 'batteryRuntime';
    public const VAR_CURRENT_FLOW = 'currentFlow';
    public const VAR_WATER_STOP = 'waterStop';
    public const VAR_SLEEP_MODE = 'sleepMode';
    public const VAR_HOLIDAY = 'holiday';
    public const VAR_ACTIVE_SCENE = 'activeScene';
    public const VAR_SALT_LEVEL = 'saltLevel';
    public const VAR_SALT_PERCENT = 'rangeSaltPercent';
    public const VAR_SALT_DAYS = 'rangeSaltDays';
    public const VAR_INPUT_HARDNESS = 'inputHardness';
    public const VAR_TARGET_HARDNESS = 'targetHardness';
    public const VAR_REGENERATION = 'Regeneration';

    // Water Scenes
    public const SCENE_NORMAL = 0;
    public const SCENE_SHOWER = 1;
    public const SCENE_HEATER = 2;
    public const SCENE_WATERING = 3;
    public const SCENE_WASHING = 4;

    // Timeouts and Limits
    public const DEFAULT_REFRESH_RATE = 60;
    public const API_TIMEOUT = 30;
    public const CONNECTION_TIMEOUT = 20;
    public const MAX_RETRIES = 3;
}