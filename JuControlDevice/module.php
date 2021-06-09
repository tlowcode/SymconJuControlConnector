<?php

declare(strict_types=1);
	class JuControlDevice extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RequireParent('{4CB91589-CE01-4700-906F-26320EFCF6C4}');
			$this->RegisterTimer("RefreshTimer", 5000, 'JCD_RefreshData();');	

			$this->RegisterPropertyString("Username", "");
			$this->RegisterPropertyString("Passwort", "");

			$this->RegisterVariableString("DeviceID", "ID des Geräts", "");
			$this->RegisterVariableString("DeviceType", "Typ des Geräts", "");

		/*
			public $deviceState = '';
			public $deviceSN = '';  
			public $targetHardness = 0;
			public $inputHardness = 0;
			public $rangeSaltPercent = 0;
			public $currentFlow = 0;
			public $batteryState = 0;
			public $activeScene = '';
			public $swVersion = '';
			public $hwVersion = '';
			public $ccuVersion = '';
			public $nextService = '';
			public $hasEmergencySupply = false;
			public $totalWater = 0;
			public $totalRegenaration = 0;
			public $totalService = 0;
			*/
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
		}

		public function Send(string $RequestMethod, string $RequestURL, string $RequestData, int $Timeout)
		{
			$this->SendDataToParent(json_encode(['DataID' => '{D4C1D08F-CD3B-494B-BE18-B36EF73B8F43}', "RequestMethod" => $RequestMethod, "RequestURL" => $RequestURL, "RequestData" => $RequestData, "Timeout" => $Timeout]));
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			IPS_LogMessage('Device RECV', utf8_decode($data->Buffer . ' - ' . $data->RequestMethod . ' - ' . $data->RequestURL . ' - ' . $data->RequestData . ' - ' . $data->Timeout));
		}

		public function RefreshData()
		{
			
			$url = 'https://www.myjudo.eu';

			$username = $this->ReadPropertyString("Username");
			$passwd = $this->ReadPropertyString("Passwort");
		
		
			$loginUrl = $url . '/interface/?group=register&command=login&name=login&user=' . $username . '&password=' . md5($passwd, false) . '&nohash=' . $passwd . '&role=customer';
		
			$this->Send('GET', $loginUrl, '', 5000);

			IPS_LogMessage($_IPS['SELF'], 'RefreshData() called! Username: '. $username . 'PW: ' . $passwd . 'URL: ' . $loginUrl);
		}
	}