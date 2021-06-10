<?php

declare(strict_types=1);
require_once('Webclient.php');

	class JuControlDevice extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RequireParent('{4CB91589-CE01-4700-906F-26320EFCF6C4}');

			$this->RegisterAttributeString("AccessToken", "noToken");

			$this->RegisterTimer("RefreshTimer", 0, 'JCD_RefreshData('. $this->InstanceID . ');');	

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
			$this->Login();

			//TODO: only if instance state is active!

			

			$url = 'https://www.myjudo.eu';

			$username = $this->ReadPropertyString("Username");
			$passwd = $this->ReadPropertyString("Passwort");
		
		
			$loginUrl = $url . '/interface/?group=register&command=login&name=login&user=' . $username . '&password=' . md5($passwd, false) . '&nohash=' . $passwd . '&role=customer';
		
			//$this->Send('GET', $loginUrl, '', 5000);

			//IPS_LogMessage($_IPS['SELF'], 'RefreshData() called! Username: '. $username . 'PW: ' . $passwd . 'URL: ' . $loginUrl);
		}

		public function Login(){

			$wc = new WebClient();
			$url = 'https://www.myjudo.eu';

			$username = $this->ReadPropertyString("Username");
			$passwd = $this->ReadPropertyString("Passwort");

			$loginUrl = $url . '/interface/?group=register&command=login&name=login&user=' . $username . '&password=' . md5($passwd, false) . '&nohash=' . $passwd . '&role=customer';
		
			IPS_LogMessage($_IPS['SELF'], 'Trying to login with username: '. $username);

			$response = $wc->Navigate($loginUrl);
			if ($response === FALSE) 
			{
				$this->SetStatus(201);
			}
			else 
			{
				$json = json_decode($response);
				if ($json->status == 'ok')
				{
					IPS_LogMessage($_IPS['SELF'], 'Login successful, Token: '. $json->token);
					$this->WriteAttributeString("AccessToken", $json->token);
					$this->SetStatus(102);
					$this->SetTimerInterval("RefreshTimer", 60 * 1000);
				}
				else
				{
					IPS_LogMessage($_IPS['SELF'], 'Login failed!');
					$this->SetStatus(201);
					$this->SetTimerInterval("RefreshTimer", 0);
				}
			}
		
		}

		public function TestConnection()
		{
			$this->Login();
		}
	}