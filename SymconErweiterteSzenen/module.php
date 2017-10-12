<?
class SymconAlarmanlage extends IPSModule {

	public function __construct($InstanceID) {
		//Never delete this line!
		parent::__construct($InstanceID);

	}

	public function Create() {
		//Never delete this line!
		parent::Create();

		if(@$this->RegisterPropertyInteger("mail") !== false)
		{
            $this->RegisterPropertyInteger("mail", 0);
            $this->RegisterPropertyInteger("wfc", 0);
		}
	}

	public function Destroy() {
		//Never delete this line!
		parent::Destroy();

	}

	public function ApplyChanges() {
		//Never delete this line!
		parent::ApplyChanges();

		if(IPS_GetParent($this->InstanceID) != 0)
		{
			$svs = $this->CreateSetValueScript();

			$this->CreateDummyByIdent(IPS_GetParent($this->InstanceID), "Sensors", "Sensoren");
			$this->CreateDummyByIdent(IPS_GetParent($this->InstanceID), "Targets", "Alarm Targets", "Warning");
			
			$this->CreateVariableByIdent($this->InstanceID, "Active", "Automatik", 0, "Switch", true, '', -5);
			$this->EnableAction("Active");

			$this->CreateIntervalProfile("Seconds");
		$this->CreateVariableByIdent($this->InstanceID, "TimerInterval", "Benachrichtigung Interval", 1, "Seconds", true, "Clock", -1, 30 /*init val*/, $svs);
			$this->EnableAction("Active");

			$vid = $this->CreateVariableByIdent($this->InstanceID, "Alert", "Alarm", 0, "Switch", true, '', -4);
			$this->EnableAction("Alert");
			$this->CreateTriggerByIdent($this->InstanceID, "AlertOnChange", "Alert.OnChange", $vid);
			
			$this->CreateVariableByIdent($this->InstanceID, "mailActive", "E-Mail Benachrichtigung", 0, "Switch", true, '', -3);
			$this->EnableAction("mailActive");

			$this->CreateVariableByIdent($this->InstanceID, "notificationActive", "Push Benachrichtigung", 0, "Switch", true, '', -2);
			$this->EnableAction("notificationActive");
			
			$this->CreateTimerByIdent($this->InstanceID, "AlertSpamTimer", "Alert Timer");}
	}

	public function UpdateEvents() {
		
		$sensorsID = $this->CreateDummyByIdent(IPS_GetParent($this->InstanceID), "Sensors", "Sensors");
		
		//We want to listen for all changes on all sensorsID
		foreach(IPS_GetChildrenIDs($sensorsID) as $sensorID) {
			//only allow links
			if(IPS_LinkExists($sensorID)) {
				if(@IPS_GetObjectIDByIdent("Sensor".$sensorID, $this->InstanceID) === false) {
					$linkVariableID = IPS_GetLink($sensorID)['TargetID'];
					if(IPS_VariableExists($linkVariableID)) {
						$eid = IPS_CreateEvent(0 /* Trigger */);
						IPS_SetParent($eid, $this->InstanceID);
						IPS_SetName($eid, "Trigger for #".$linkVariableID);
						IPS_SetIdent($eid, "Sensor".$sensorID);
						IPS_SetEventTrigger($eid, 1, $linkVariableID);
						IPS_SetEventScript($eid, "SA_TriggerAlert(\$_IPS['TARGET'], \$_IPS['VARIABLE'], \$_IPS['VALUE']);\n
												  if(GetValue($linkVariableID) !== false && GetValue(IPS_GetObjectIDByIdent(\"mailActive\", ".$this->InstanceID.")) === true)\n
												  { 
													\$subject = \"". IPS_GetName($this->InstanceID) .": \" . IPS_GetName($linkVariableID) . \" (\" .  date(\"m.d.y\") . \" um \" . date(\"H:i:s\") .\")\";\n
													SMTP_SendMail(IPS_GetProperty(". $this->InstanceID .", 'mail'), \$subject, \$subject);\n
												  }");
						IPS_SetEventActive($eid, true);
					}
				}
			}
		}

	}
	
	public function TriggerAlert(int $SourceID, int $SourceValue) {
		
		//Only enable alarming if our module is active
		if(!GetValue($this->GetIDForIdent("Active"))) {
			return;
		}
		
		switch($this->GetProfileName(IPS_GetVariable($SourceID))) {
			case "~Window.Hoppe":
				if($SourceValue == 0 || $SourceValue == 2) {
					$this->SetAlert(true, $SourceID, $SourceValue);
				}
				break;
			case "~Window.HM":
				if($SourceValue == 1 || $SourceValue == 2) {
					$this->SetAlert(true, $SourceID, $SourceValue);
				}
				break;
			case "~Lock.Reversed":
			case "~Battery.Reversed":
			case "~Presence.Reversed":
			case "~Window.Reversed":
				if(!$SourceValue) {
					$this->SetAlert(true, $SourceID, $SourceValue);
				}
				break;
			default:
				if($SourceValue) {
					$this->SetAlert(true, $SourceID, $SourceValue);
				}
				break;
		}

	}

	public function SetAlert(bool $Status, int $SourceID = null, int $SourceValue = null) {
		
		$targetsID = $this->CreateDummyByIdent(IPS_GetParent($this->InstanceID), "Targets", "Alert Target");
		
		//Lets notify all target devices
		foreach(IPS_GetChildrenIDs($targetsID) as $targetID) {
			//only allow links
			if (IPS_LinkExists($targetID)) {
				$linkVariableID = IPS_GetLink($targetID)['TargetID'];
				if (IPS_VariableExists($linkVariableID)) {
					$o = IPS_GetObject($linkVariableID);
					$v = IPS_GetVariable($linkVariableID);

					$actionID = $this->GetProfileAction($v);
					$profileName = $this->GetProfileName($v);

					//If we somehow do not have a profile take care that we do not fail immediately
					if($profileName != "") {
						//If we are enabling analog devices we want to switch to the maximum value (e.g. 100%)
						if ($Status) {
							$actionValue = IPS_GetVariableProfile($profileName)['MaxValue'];
						} else {
							$actionValue = 0;
						}
						//Reduce to boolean if required
						if($v['VariableType'] == 0) {
							$actionValue = $actionValue > 0;
						}
					} else {
						$actionValue = $Status;
					}
					
					if(IPS_InstanceExists($actionID)) {
						IPS_RequestAction($actionID, $o['ObjectIdent'], $actionValue);
					} else if(IPS_ScriptExists($actionID)) {
						echo IPS_RunScriptWaitEx($actionID, Array("VARIABLE" => $linkVariableID, "VALUE" => $actionValue));
					} else if($actionID < 10000 && GetValue($linkVariableID) != $actionValue) {
						SetValue($linkVariableID, $actionValue);
					}
				}
			}
		}
        
        $notificationActive = GetValue(IPS_GetObjectIDByIdent("notificationActive", $this->InstanceID));
        //Send a notificatin message to the configured WebFront
		if($Status && $notificationActive)
		{
			IPS_LogMessage("Alarm", "Notifications enabled");
			$tid = $this->CreateTimerByIdent($this->InstanceID, "AlertSpamTimer", "Alert Timer");
			IPS_SetEventActive($tid, true);
			$statusMsg = "true";
		}
		else
			$statusMsg = "false";
		IPS_LogMessage("SymconAlarmanlage", "Der alarm wurde auf ". $statusMsg ." gesetzt");
		SetValue($this->GetIDForIdent("Alert"), $Status);
	}
	

	public function SetActive(bool $Value) {
		
		SetValue($this->GetIDForIdent("Active"), $Value);
		
		if(!$Value) {
			$this->SetAlert(false);
		}
		
    }
    
    private function NotificationTimer($value)
    {
		SetValue($this->GetIDForIdent("notificationActive"), $value);

        if($value === false)
        {
            $tid = $this->CreateTimerByIdent($this->InstanceID, "AlertSpamTimer", "Alert Timer");
            IPS_SetEventActive($tid, $value);
		}
	}
	
	private function SetTimer($value) {
		$tid = IPS_GetObjectIDByIdent("AlertSpamTimer", $this->InstanceID);
		IPS_SetEventCyclic($tid, 0 /* Keine Datumsüberprüfung */, 0, 0, 2, 1 /* Sekündlich */ , $value /* Alle $value Sekunden */);
	}

	public function RequestAction($Ident, $Value) {
		
		switch($Ident) {
			case "Active":
				$this->SetActive($Value);
				break;
			case "Alert":
				$this->SetAlert($Value);
                break;
            case "mailActive":
				SetValue($this->GetIDForIdent("mailActive"), $Value);
                break;
            case "notificationActive":
                $this->NotificationTimer($Value);
				break;
			default:
				throw new Exception("Invalid ident");
		}
	
	}

	private function GetProfileName($variable) {
		
		if($variable['VariableCustomProfile'] != "")
			return $variable['VariableCustomProfile'];
		else
			return $variable['VariableProfile'];
	}

	private function GetProfileAction($variable) {
		
		if($variable['VariableCustomAction'] != "")
			return $variable['VariableCustomAction'];
		else
			return $variable['VariableAction'];
	}
	
	private function CreateDummyByIdent($id, $ident, $name, $icon = "") {
		
		 $cid = @IPS_GetObjectIDByIdent($ident, $id);
		 if($cid === false)
		 {
             $dummyGUID = $this->GetModuleIDByName();
			 $cid = IPS_CreateInstance($dummyGUID);
			 IPS_SetParent($cid, $id);
			 IPS_SetName($cid, $name);
			 IPS_SetIdent($cid, $ident);
			 IPS_SetIcon($cid, $icon);
		 }
		 return $cid;
    }
	
	private function CreateVariableByIdent($id, $ident, $name, $type, $profile = "", $enableLogging = false, $icon = '', $pos = 0, $initVal = 0, $action = 0) {
		
		 $vid = @IPS_GetObjectIDByIdent($ident, $id);
		 if($vid === false)
		 {
			 $vid = IPS_CreateVariable($type);
			 IPS_SetParent($vid, $id);
			 IPS_SetIdent($vid, $ident);
			 IPS_SetIcon($vid, $icon);
			 IPS_SetPosition($vid, $pos);
			 if($action != 0)
			 {
				 IPS_SetVariableCustomAction($vid, $action);
			 }
			 if($profile != "")
                IPS_SetVariableCustomProfile($vid, $profile);
            if($enableLogging)
            {
                $archivGUID = $this->GetModuleIDByName("Archive Control");
                $archivIDs = (array) IPS_GetInstanceListByModuleID($archivGUID);
                AC_SetLoggingStatus($archivIDs[0], $vid, true);
			}
			if($initVal != 0)
			{
				SetValue($vid, $initVal);
			}
		 }
		 IPS_SetName($vid, $name);
		 return $vid;
	}
	
	private function CreateTimerByIdent($id, $ident, $name) {
		
		 $tid = @IPS_GetObjectIDByIdent($ident, $id);
		 if($tid === false)
		 {
			 $tid = IPS_CreateEvent(1);
			 IPS_SetParent($tid, $id);
			 IPS_SetName($tid, $name);
			 IPS_SetIdent($tid, $ident);
			 $WebFrontInsIDs = $this->GetModuleIDByName("WebFront Configurator");
			 $currentName = IPS_GetName($this->InstanceID);
			 $currentID = $this->InstanceID;
			 $WebFrontID = $this->ReadPropertyInteger("wfc");
			 $script = "\$sensorLinks = IPS_GetObjectIDByIdent('Sensors', ". IPS_GetParent($this->InstanceID) .");\n";
			 $script .= "foreach(IPS_GetChildrenIDs(\$sensorLinks) as \$c) {\n";
			 $script .= "\$sensorVal = GetValue(IPS_GetLink(\$c)['TargetID']);\n";
			 $script .= "if(\$sensorVal === true) {\n";
			 $script .= "\$sensor = \$c; break;\n";
			 $script .= "}\n";
			 $script .= "}\n";
			 $script .= "if(\$sensor != 0)\n";
			 $script .= "\$subject = \"". IPS_GetName($this->InstanceID) .": \" . IPS_GetName(\$sensor) . \" (\" .  date(\"m.d.y\") . \" um \" . date(\"H:i:s\") .\")\";\n";
			 $script .= "else\n";
			 $script .= "\$subject = \"". IPS_GetName($this->InstanceID) .": \" . \" Alarm \" . \" (\" .  date(\"m.d.y\") . \" um \" . date(\"H:i:s\") .\")\";\n";
			 $script .= "WFC_PushNotification($WebFrontID, 'Alarm', \$subject, '', 0); ";
			 IPS_SetEventScript($tid, $script);
			 IPS_SetEventCyclic($tid, 0 /* Keine Datumsüberprüfung */, 0, 0, 2, 1 /* Sekündlich */ , 30 /* Alle 30 Sekunden */);
             IPS_SetEventActive($tid, false);
             IPS_SetHidden($tid, true);
		 }
		 return $tid;
	}

	private function CreateTriggerByIdent($id, $ident, $name, $target, $type = 1, $value = null) {
		
		 $eid = @IPS_GetObjectIDByIdent($ident, $id);
		 if($eid === false)
		 {
			 $eid = IPS_CreateEvent(0);
			 IPS_SetParent($eid, $id);
			 IPS_SetName($eid, $name);
             IPS_SetIdent($eid, $ident);
             if($ident == "AlertOnChange")
             {
                $script = "\$id = IPS_GetObjectIDByIdent('AlertSpamTimer', $id);\n";
				$script .= "if(GetValue($target) === false) IPS_SetEventActive(\$id, false);";
             }
			 IPS_SetEventScript($eid, $script);
			 IPS_SetEventTrigger($eid, $type, $target);
             IPS_SetEventActive($eid, true);
             if($value !== null)
             {
                IPS_SetEventTriggerValue($eid, $value);
             }
		 }
		 return $eid;
	}
	
	private function GetModuleIDByName($name = "Dummy Module")
	{
		$moduleList = IPS_GetModuleList();
		$GUID = ""; //init
		foreach($moduleList as $l)
		{
			if(IPS_GetModule($l)['ModuleName'] == $name)
			{
				$GUID = $l;
				break;
			}
		}
		
		return $GUID;
	}

	private function CreateIntervalProfile($name)
	{
		if(!IPS_VariableProfileExists($name))
		{
			IPS_CreateVariableProfile($name, 1 /*int*/);
			IPS_SetVariableProfileText($name, "", "s");
			IPS_SetVariableProfileValues($name, 0, 240, 1);
		}
	}

	private function CreateSetValueScript()
	{
		$svs = @IPS_GetObjectIDByIdent("SetValueScript", $this->InstanceID);
		if($svs === false)
		{
			$svs = IPS_CreateScript(0);
			IPS_SetScriptContent($svs, "<?
SetValue(\$_IPS['VARIABLE'], \$_IPS['VALUE']);

\$tid = IPS_GetObjectIDByIdent(\"AlertSpamTimer\",". $this->InstanceID .");
IPS_SetEventCyclic(\$tid, 0 /* Keine Datumsüberprüfung */, 0, 0, 2, 1 /* Sekündlich */ , \$_IPS['VALUE']);

?>");
			IPS_SetName($svs, "SetValue");
			IPS_SetParent($svs, $this->InstanceID);
			IPS_SetPosition($svs, 9999);
			IPS_SetIdent($svs, "SetValueScript");
			IPS_SetHidden($svs, true);
		}
		return $svs;
	}
}
?>
