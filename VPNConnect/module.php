<?
    // Klassendefinition
    class VPNConnect extends IPSModule 
    {
	 // https://mpd.readthedocs.io/en/stable/protocol.html#command-reference
	    
	// Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
        {
            	// Diese Zeile nicht löschen.
            	parent::Create();
		$this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
            	$this->RegisterPropertyBoolean("Open", false);
		$this->RegisterPropertyString("IPAddress", "127.0.0.1");
		$this->RegisterPropertyInteger("Port", 6600);
		$this->RegisterPropertyString("RadioStations", "");
		$this->RegisterTimer("Status", 0, 'MusicPlayerDaemon_Status($_IPS["TARGET"]);');
		
		// Profile anlegen
		$this->RegisterProfileInteger("MusicPlayerDaemon.RadioStations_".$this->InstanceID, "Melody", "", "", 0, 10, 0);
		$this->RegisterMediaObject("Logo", "Logo_".$this->InstanceID, 1, $this->InstanceID, 100, true, "Logo.png");
		
		
		$this->RegisterProfileInteger("MusicPlayerDaemon.Remote", "Remote", "", "", 0, 2, 0);
		IPS_SetVariableProfileAssociation("MusicPlayerDaemon.Remote", 1, "Stop", "Remote", -1);
		IPS_SetVariableProfileAssociation("MusicPlayerDaemon.Remote", 2, "Pause", "Remote", -1);
		IPS_SetVariableProfileAssociation("MusicPlayerDaemon.Remote", 3, "Play", "Remote", -1);
		
		// Status-Variablen anlegen
		$this->RegisterVariableInteger("LastKeepAlive", "Letztes Keep Alive", "~UnixTimestamp", 10);
		
		$this->RegisterVariableInteger("Volume","Volume","~Intensity.100", 50);
		$this->EnableAction("Volume");
		
		$this->RegisterVariableInteger("Remote", "Remote", "MusicPlayerDaemon.Remote", 50);
		$this->EnableAction("Remote");
		
		$this->RegisterVariableInteger("RadioStations", "Radiosender", "MusicPlayerDaemon.RadioStations_".$this->InstanceID, 60);
		$this->EnableAction("RadioStations");
		
		$this->RegisterVariableString("Title", "Titel", "", 70);

        }
       	
	public function GetConfigurationForm() { 
		$arrayStatus = array(); 
		$arrayStatus[] = array("code" => 101, "icon" => "inactive", "caption" => "Instanz wird erstellt"); 
		$arrayStatus[] = array("code" => 102, "icon" => "active", "caption" => "Instanz ist aktiv");
		$arrayStatus[] = array("code" => 104, "icon" => "inactive", "caption" => "Instanz ist inaktiv");
		$arrayStatus[] = array("code" => 200, "icon" => "error", "caption" => "Instanz ist fehlerhaft"); 
		$arrayStatus[] = array("code" => 202, "icon" => "error", "caption" => "Kommunikationfehler!");
		
		$arrayElements = array(); 
		$arrayElements[] = array("name" => "Open", "type" => "CheckBox",  "caption" => "Aktiv"); 
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "IPAddress", "caption" => "IP");
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "Port", "caption" => "Port (1 - 65535)", "minimum" => 1, "maximum" => 65535);
		
		$arrayElements[] = array("type" => "Label", "caption" => "Radio-Sender");
		$arraySort = array();
		$arraySort = array("column" => "RadioStationName", "direction" => "ascending");
		
		$arrayEditName = array();
		$arrayEditName = array("type" => "ValidationTextBox");
		
		$arrayEditLink = array();
		$arrayEditLink = array("type" => "ValidationTextBox");
		
		$arrayEditLogo = array();
		$arrayEditLogo = array("type" => "SelectMedia");
		
		$arrayColumns = array();
		$arrayColumns[] = array("label" => "Stationsname", "name" => "RadioStationName", "width" => "300px", "add" => "Radio GaGa", "edit" => $arrayEditName);
		$arrayColumns[] = array("label" => "Link", "name" => "RadioStationLink", "width" => "500px", "add" => "http", "edit" => $arrayEditLink, "align" => "left");
		$arrayColumns[] = array("label" => "Logo", "name" => "RadioStationLogo", "width" => "300px", "add" => 0, "edit" => $arrayEditLogo, "align" => "right");

		
		$arrayElements[] = array("type" => "List", "name" => "RadioStations", "rowCount" => 10, "add" => true, "delete" => true, "sort" => $arraySort, "columns" => $arrayColumns);

				
		$arrayActions = array(); 
		$arrayActions[] = array("type" => "Label", "label" => "Test Center"); 
		$arrayActions[] = array("type" => "TestCenter", "name" => "TestCenter");
		
 		return JSON_encode(array("status" => $arrayStatus, "elements" => $arrayElements, "actions" => $arrayActions)); 		 
 	} 
	
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
        {
                // Diese Zeile nicht löschen
                parent::ApplyChanges();
		
		$Content = file_get_contents(__DIR__ . '/../imgs/MPD_Logo.png'); 
		IPS_SetMediaContent($this->GetIDForIdent("Logo_".$this->InstanceID), base64_encode($Content));  //Bild Base64 codieren und ablegen
		IPS_SendMediaEvent($this->GetIDForIdent("Logo_".$this->InstanceID)); //aktualisieren
		
		If (IPS_GetKernelRunlevel() == KR_READY) {
			$ParentID = $this->GetParentID();
			If ($ParentID > 0) {
				If (IPS_GetProperty($ParentID, 'Host') <> $this->ReadPropertyString('IPAddress')) {
		                	IPS_SetProperty($ParentID, 'Host', $this->ReadPropertyString('IPAddress'));
				}
				If (IPS_GetProperty($ParentID, 'Port') <> $this->ReadPropertyInteger('Port')) {
		                	IPS_SetProperty($ParentID, 'Port', $this->ReadPropertyInteger('Port'));
				}
				If (IPS_GetProperty($ParentID, 'Open') <> $this->ReadPropertyBoolean("Open")) {
		                	IPS_SetProperty($ParentID, 'Open', $this->ReadPropertyBoolean("Open"));
				}
				
				if(IPS_HasChanges($ParentID))
				{
				    	$Result = @IPS_ApplyChanges($ParentID);
					If ($Result) {
						$this->SendDebug("ApplyChanges", "Einrichtung des Client Socket erfolgreich", 0);
					}
					else {
						$this->SendDebug("ApplyChanges", "Einrichtung des Client Socket nicht erfolgreich!", 0);
					}
				}
			}
			
			If ($this->GetValue("Remote") <> 1) {
				$this->SetValue("Remote", 1);
			}
			If ($this->GetValue("Title") <> "-") {
				$this->SetValue("Title", "-"); 
			}
			
			If ($this->ReadPropertyBoolean("Open") == true) {
				If ($this->ConnectionTest() == true) {
					If ($this->GetStatus() <> 102) {
						$this->SetStatus(102);
					}
					$this->SetRadioStationsAssociations();
					$this->Status();
					$this->SetTimerInterval("Status", 3 * 1000);
				}
			}
			else {
				If ($this->GetStatus() <> 104) {
					$this->SetStatus(104);
				}
				$this->SetTimerInterval("Status", 0);
			}	   
		}
		
		
		
	}
	
	public function RequestAction($Ident, $Value) 
	{
  		switch($Ident) {
			case "Volume":
				$this->SetVolume($Value);
				break;
			case "Remote":
				If ($Value == 1) {
					$this->Stop();
					$Content = file_get_contents(__DIR__ . '/../imgs/MPD_Logo.png'); 
					IPS_SetMediaContent($this->GetIDForIdent("Logo_".$this->InstanceID), base64_encode($Content));  //Bild Base64 codieren und ablegen
					IPS_SendMediaEvent($this->GetIDForIdent("Logo_".$this->InstanceID)); //aktualisieren
				} 
				elseIf ($Value == 2) { 
					$this->Pause(1);
				}
				elseIf ($Value == 3) { 
					$this->Play();
					$this->ShowLogo($this->GetValue("RadioStations") );
				}
				$this->SetValue($Ident, $Value);
				break;
			case "RadioStations":
				$RadioStationsString = $this->ReadPropertyString("RadioStations");
				$RadioStations = json_decode($RadioStationsString);
				$i = 0;
				foreach ($RadioStations as $Key => $Link) {
					If ($i == $Value) {
						//$this->SendDebug("RequestAction", "Radiostationslink: ".$Link->RadioStationLink, 0);
						$this->SetNewStation($Link->RadioStationLink);
						$this->ShowLogo($Value);
					}
					$i++;
				}
				$this->SetValue($Ident, $Value);
				
				break;
	      		
	        default:
	            throw new Exception("Invalid Ident");
	    	}
	}
	    
	public function ReceiveData($JSONString) 
	{
		// Empfangene Daten vom I/O
	    	$Data = json_decode($JSONString);
		$Message = utf8_decode($Data->Buffer);
		$Message = trim($Message, "\x00..\x1F");			
		$this->SendDebug("ReceiveData", $Message, 0);
		$MessageParts = explode(PHP_EOL, $Message);
		$this->SetValue("LastKeepAlive", time() );
		
		for ($i = 0; $i < Count($MessageParts); $i++) {
			$MessageValue = explode(":", $MessageParts[$i]);
			//$this->SendDebug("ReceiveData", "MessageValue: ".$MessageValue[0], 0);
			
			switch($MessageValue[0]) {
				case preg_match('/OK MPD.*/', $MessageValue[0]) ? $MessageValue[0] : !$MessageValue[0]:
					$this->SendDebug("ReceiveData", "Version: ".$MessageValue[0], 0);
					break;
				case preg_match('/ACK.*/', $MessageValue[0]) ? $MessageValue[0] : !$MessageValue[0]:
					$this->SendDebug("ReceiveData", "ACK: Ein Fehler ist aufgetreten!", 0);
					break;
				case "OK":
					$this->SendDebug("ReceiveData", "OK: Befehl erfolgreich", 0);
					break;
				case "error":
					array_shift($MessageValue);
					$MessageDisplay = implode(":", $MessageValue);
					$this->SendDebug("ReceiveData", "Fehler: ".$MessageDisplay, 0);
					break;
				case "volume":
					$this->SendDebug("ReceiveData", "Volume: ".intval($MessageValue[1]), 0);
					If ($this->GetValue("Volume") <> intval($MessageValue[1])) {
						$this->SetValue("Volume", intval($MessageValue[1]));
					}
					break;
				case "repeat":
					$this->SendDebug("ReceiveData", "Repeat: ".intval($MessageValue[1]), 0);
					break;
				case "random":
					$this->SendDebug("ReceiveData", "Random: ".intval($MessageValue[1]), 0);
					break;
				case "single":
					$this->SendDebug("ReceiveData", "Single: ".intval($MessageValue[1]), 0);
					break;
				case "consume":
					$this->SendDebug("ReceiveData", "Consume: ".intval($MessageValue[1]), 0);
					break;
				case "partition":
					$this->SendDebug("ReceiveData", "Partition: ".trim($MessageValue[1]), 0);
					break;
				case "playlist":
					$this->SendDebug("ReceiveData", "Playlist: ".intval($MessageValue[1]), 0);
					break;
				case "playlistlength":
					$this->SendDebug("ReceiveData", "Playlistlength: ".intval($MessageValue[1]), 0);
					break;
				case "mixrampdb":
					$this->SendDebug("ReceiveData", "Mixrampdb: ".floatval($MessageValue[1]), 0);
					break;
				case "state":
					$this->SendDebug("ReceiveData", "State: ".trim($MessageValue[1]), 0);
					$MessageValue[1] = trim($MessageValue[1]);
					If ($MessageValue[1] == "play") {
						If ($this->GetValue("Remote") <> 3) {
							$this->SetValue("Remote", 3);
						}
						$this->CurrentSong();
					}
					elseif ($MessageValue[1] == "pause") {
						If ($this->GetValue("Remote") <> 2) {
							$this->SetValue("Remote", 2);
						}
					}
					elseif ($MessageValue[1] == "stop") {
						If ($this->GetValue("Remote") <> 1) {
							$this->SetValue("Remote", 1);
						}
						If ($this->GetValue("Title") <> "-") {
							$this->SetValue("Title", "-"); 
						}
						
					}
						
					break;
				case "song":
					$this->SendDebug("ReceiveData", "Song: ".intval($MessageValue[1]), 0);
					break;
				case "songid":
					$this->SendDebug("ReceiveData", "Songid: ".intval($MessageValue[1]), 0);
					break;
				case "time":
					$this->SendDebug("ReceiveData", "Time: ".intval($MessageValue[1]), 0);
					break;
				case "elapsed":
					$this->SendDebug("ReceiveData", "Elapsed: ".floatval($MessageValue[1]), 0);
					break;
				case "bitrate":
					$this->SendDebug("ReceiveData", "Bitrate: ".intval($MessageValue[1]), 0);
					break;
				case "audio":
					array_shift($MessageValue);
					$MessageDisplay = implode(":", $MessageValue);
					$this->SendDebug("ReceiveData", "Audio: ".trim($MessageDisplay), 0);
					break;
				case "file":
					array_shift($MessageValue);
					$MessageDisplay = implode(":", $MessageValue);
					$this->SendDebug("ReceiveData", "File: ".trim($MessageDisplay), 0);
					break;
				case "Title":
					$this->SendDebug("ReceiveData", "Titel: ".trim($MessageValue[1]), 0);
					$MessageValue[1] = trim($MessageValue[1]);
					If ($this->GetValue("Title") <> $MessageValue[1]) {
						$this->SetValue("Title", $MessageValue[1]); 
					}
					break;
				case "Name":
					$this->SendDebug("ReceiveData", "Name: ".trim($MessageValue[1]), 0);
					break;
				case "Pos":
					$this->SendDebug("ReceiveData", "Pos: ".intval($MessageValue[1]), 0);
					break;
				case "Id":
					$this->SendDebug("ReceiveData", "Id: ".intval($MessageValue[1]), 0);
					break;
				case "outputid":
					$this->SendDebug("ReceiveData", "Audio Output ID: ".intval($MessageValue[1]), 0);
					break;	
				case "outputname":
					$this->SendDebug("ReceiveData", "Audio Output Name: ".trim($MessageValue[1]), 0);
					break;
				case "plugin":
					$this->SendDebug("ReceiveData", "Audio Output Plugin: ".trim($MessageValue[1]), 0);
					break;	
				case "outputenabled":
					$this->SendDebug("ReceiveData", "Audio Output Enabled: ".intval($MessageValue[1]), 0);
					break;		
					
			}
		}
		
		
	}
	    
	// Beginn der Funktionen
	public function Status()
	{
		$this->SendCommand("status\n");
	}
	    
	public function Play() 
	{
		$this->SendCommand("play\n");
	}

	public function Pause(int $State) 
	{
		$this->SendCommand("pause ".$State."\n");
	}

	public function Stop() 
	{
		$this->SendCommand("stop\n");
	}

	public function Previous() 
	{
		$this->SendCommand("previous\n");
	}

	public function Next() 
	{
		$this->SendCommand("next\n");
	}
	    
	public function CurrentSong() 
	{
		$this->SendCommand("currentsong\n");
	}

	public function SetNewStation(String $StationURL) 
	{
		$this->SendCommand("clear\n");
		$this->SendCommand("add ".$StationURL." \n");
		usleep(50000);
		$this->Play();
		$this->Status();
	}

	public function SetVolume(int $Volume) 
	{
		$this->SendCommand("setvol ".$Volume."\n");
	}
	
	public function Outputs() 
	{
		$this->SendCommand("outputs\n");
	}
	
	public function Albumart() 
	{
		$this->SendCommand("albumart "."http://icecast.ndr.de/ndr/ndr2/hamburg/mp3/128/stream.mp3"." 0\n");
	}

	public function SendCommand(string $Command)
	{
		If (($this->HasActiveParent()) AND ($this->ReadPropertyBoolean("Open") == true)) {
			$this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $Command)));
		}
		else {
			$this->SendDebug("SendCommand", "Voraussetzungen nicht erfuellt!", 0);
		}
	}
	
	private function SetRadioStationsAssociations()
	{
		// Aktuelles Profil aufräumen
		$this->SendDebug("SetRadioStationsAssociations", "Ausfuehrung", 0);
		$ProfilArray = Array();
		$ProfilArray = IPS_GetVariableProfile("MusicPlayerDaemon.RadioStations_".$this->InstanceID);
		foreach ($ProfilArray["Associations"] as $Association)
		{
			@IPS_SetVariableProfileAssociation("MusicPlayerDaemon.RadioStations_".$this->InstanceID, $Association["Value"], "", "", -1);
		}
		
		$RadioStationsString = $this->ReadPropertyString("RadioStations");
		$RadioStations = json_decode($RadioStationsString);
		$this->SendDebug("SetRadioStationsAssociations", serialize($RadioStations), 0);
		
		$i = 0;
		foreach ($RadioStations as $Key => $Value) {
			$this->SendDebug("SetRadioStationsAssociations", $Value->RadioStationName." mit Link ".$Value->RadioStationLink." hinzugefuegt", 0);
			IPS_SetVariableProfileAssociation("MusicPlayerDaemon.RadioStations_".$this->InstanceID, $i, $Value->RadioStationName, "Melody", -1);
			$i++;
		}
		
	}  
	    
	private function ShowLogo(int $RadioStation)
	{
		$RadioStationsString = $this->ReadPropertyString("RadioStations");
		$RadioStations = json_decode($RadioStationsString);
		$i = 0;
		foreach ($RadioStations as $Key => $Media) {
			If ($i == $RadioStation) {
				If ($Media->RadioStationLogo > 0) {
					$this->SendDebug("ShowLogo", "Media-Objekt-ID: ".$Media->RadioStationLogo, 0);
					$Content = base64_decode(IPS_GetMediaContent($Media->RadioStationLogo));
				}
				else {
					$Content = file_get_contents(__DIR__ . '/../imgs/MPD_Logo.png'); 
				}
				IPS_SetMediaContent($this->GetIDForIdent("Logo_".$this->InstanceID), base64_encode($Content));  //Bild Base64 codieren und ablegen
				IPS_SendMediaEvent($this->GetIDForIdent("Logo_".$this->InstanceID)); //aktualisieren
			}
			$i++;
		}
	}
	    
	private function ConnectionTest()
	{
	      	$result = false;
		$IPAddress = $this->ReadPropertyString("IPAddress");
		$Port = $this->ReadPropertyInteger("Port");
	      	If (Sys_Ping($IPAddress, 300)) {
			$status = @fsockopen($IPAddress, $Port, $errno, $errstr, 10);
			if (!$status) {
				$this->SendDebug("ConnectionTest", "Port ".$Port." ist geschlossen!", 0);
				IPS_LogMessage("MusicPlayerDaemon","Port ".$Port." ist geschlossen!");
				If ($this->GetStatus() <> 202) {
					$this->SetStatus(202);
				}
			}
		      	else {
				$result = true;
				If ($this->GetStatus() <> 102) {
					$this->SetStatus(102);
				}
			}
		}
		else {
			$this->SendDebug("ConnectionTest", "IP ".$IPAddress." reagiert nicht!", 0);
			IPS_LogMessage("MusicPlayerDaemon","IP ".$IPAddress." reagiert nicht!");
			If ($this->GetStatus() <> 202) {
				$this->SetStatus(202);
			}
		}
	return $result;
	}
	
	private function GetParentID()
	{
		$ParentID = (IPS_GetInstance($this->InstanceID)['ConnectionID']);  
	return $ParentID;
	}
	
	private function RegisterMediaObject($Name, $Ident, $Typ, $Parent, $Position, $Cached, $Filename)
	{
		$MediaID = @$this->GetIDForIdent($Ident);
		if($MediaID === false) {
		    	$MediaID = 0;
		}
		
		if ($MediaID == 0) {
			 // Image im MedienPool anlegen
			$MediaID = IPS_CreateMedia($Typ); 
			// Medienobjekt einsortieren unter Kategorie $catid
			IPS_SetParent($MediaID, $Parent);
			IPS_SetIdent($MediaID, $Ident);
			IPS_SetName($MediaID, $Name);
			IPS_SetPosition($MediaID, $Position);
                    	IPS_SetMediaCached($MediaID, $Cached);
			$ImageFile = IPS_GetKernelDir()."media".DIRECTORY_SEPARATOR.$Filename;  // Image-Datei
			IPS_SetMediaFile($MediaID, $ImageFile, false);    // Image im MedienPool mit Image-Datei verbinden
		}  
	}     
	    
	private function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
	{
	        if (!IPS_VariableProfileExists($Name))
	        {
	            IPS_CreateVariableProfile($Name, 1);
	        }
	        else
	        {
	            $profile = IPS_GetVariableProfile($Name);
	            if ($profile['ProfileType'] != 1)
	                throw new Exception("Variable profile type does not match for profile " . $Name);
	        }
	        IPS_SetVariableProfileIcon($Name, $Icon);
	        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
	        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);        
	}
	    
	private function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits)
	{
	        if (!IPS_VariableProfileExists($Name))
	        {
	            IPS_CreateVariableProfile($Name, 2);
	        }
	        else
	        {
	            $profile = IPS_GetVariableProfile($Name);
	            if ($profile['ProfileType'] != 2)
	                throw new Exception("Variable profile type does not match for profile " . $Name);
	        }
	        IPS_SetVariableProfileIcon($Name, $Icon);
	        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
	        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
	        IPS_SetVariableProfileDigits($Name, $Digits);
	}
}
?>
