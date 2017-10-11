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
		}
	}

	public function Destroy() {
		//Never delete this line!
		parent::Destroy();

	}

	public function ApplyChanges() {
		//Never delete this line!
		parent::ApplyChanges();

		$this->CreateCategoryByIdent($this->InstanceID, "Sensors", "Sensors");
		$this->CreateCategoryByIdent($this->InstanceID, "Targets", "Alert Target");
		
		$this->CreateVariableByIdent($this->InstanceID, "Active", "Active", 0, "~Switch");
		$this->EnableAction("Active");
		$vid = $this->CreateVariableByIdent($this->InstanceID, "Alert", "Alert", 0, "~Alert");
		$this->EnableAction("Alert");
		$this->CreateTriggerByIdent($this->InstanceID, "AlertOnChange", "Alert.OnChange", $vid);
		
		$this->CreateTimerByIdent($this->InstanceID, "AlertSpamTimer", "Alert Timer");
	}

	public function UpdateEvents() {
		
		$sensorsID = $this->CreateCategoryByIdent($this->InstanceID, "Sensors", "Sensors");
		
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
						IPS_SetEventTrigger($eid, 0, $linkVariableID);
						IPS_SetEventScript($eid, "SA_TriggerAlert(\$_IPS['TARGET'], \$_IPS['VARIABLE'], \$_IPS['VALUE']);");
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
		
		$targetsID = $this->CreateCategoryByIdent($this->InstanceID, "Targets", "Alert Target");
		
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
					}
				}
			}
		}
		
		if($Status)
		{
			$tid = $this->CreateTimerByIdent($this->InstanceID, "AlertSpamTimer", "Alert Timer");
			IPS_SetEventActive($tid, true);
			$statusMsg = "true";
		}
		else
			$statusMsg = "false";
		IPS_LogMessage("SymconAlarmanlage", "Der alarm wurde auf ". $statusMsg ." gesetzt");
		SetValue($this->GetIDForIdent("Alert"), $Status);
		
		//Send an e-mail to the recipient about the Alert
		if($this->ReadPropertyInteger("mail") > 9999 && $Status)
		{
			IPS_LogMessage("SymconAlarmanlage", "Sending mail to address specified in Linked SMTP Instance (" . $this->ReadPropertyInteger("mail") . ")");
			$subject = "Der Alarm für " . IPS_GetName($this->InstanceID) . "(". $this->InstanceID .") wurde ausgelöst";
			$message = "Der auslöser war " . IPS_GetName($SourceID) . "(" . $SourceID . ") mit dem Wert " . $SourceValue . ". Es wurde am " . date("m.d.y") . " um " . date("H:i:s") . " ausgelöst.";
			SMTP_SendMail($this->ReadPropertyInteger("mail"), $subject, $message);
		}
	}
	

	public function SetActive(bool $Value) {
		
		SetValue($this->GetIDForIdent("Active"), $Value);
		
		if(!$Value) {
			$this->SetAlert(false);
		}
		
	}

	public function RequestAction($Ident, $Value) {
		
		switch($Ident) {
			case "Active":
				$this->SetActive($Value);
				break;
			case "Alert":
				$this->SetAlert($Value);
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
	
	private function CreateCategoryByIdent($id, $ident, $name) {
		
		 $cid = @IPS_GetObjectIDByIdent($ident, $id);
		 if($cid === false)
		 {
			 $cid = IPS_CreateCategory();
			 IPS_SetParent($cid, $id);
			 IPS_SetName($cid, $name);
			 IPS_SetIdent($cid, $ident);
		 }
		 return $cid;
	}
	
	private function CreateVariableByIdent($id, $ident, $name, $type, $profile = "") {
		
		 $vid = @IPS_GetObjectIDByIdent($ident, $id);
		 if($vid === false)
		 {
			 $vid = IPS_CreateVariable($type);
			 IPS_SetParent($vid, $id);
			 IPS_SetName($vid, $name);
			 IPS_SetIdent($vid, $ident);
			 if($profile != "")
				IPS_SetVariableCustomProfile($vid, $profile);
		 }
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
			 $script = "";
			 foreach($WebFrontInsIDs as $insID)
			{
				$script .= "WFC_PushNotification($insID, 'Alarm', 'Der alarm für $currentName ($currentID) wurde ausgelößt', '', 0); ";
			}
			 IPS_SetEventScript($tid, $script);
			 IPS_SetEventCyclic($tid, 0 /* Keine Datumsüberprüfung */, 0, 0, 2, 1 /* Sekündlich */ , 5 /* Alle 5 Sekunden */);
			 IPS_SetEventActive($tid, false);
		 }
		 return $tid;
	}

	private function CreateTriggerByIdent($id, $ident, $name, $target) {
		
		 $eid = @IPS_GetObjectIDByIdent($ident, $id);
		 if($eid === false)
		 {
			 $eid = IPS_CreateEvent(0);
			 IPS_SetParent($eid, $id);
			 IPS_SetName($eid, $name);
			 IPS_SetIdent($eid, $ident);
			 $script = "\$id = IPS_GetObjectIDByIdent('AlertSpamTimer', $id);\n";
			 $script .= "if(GetValue($target)) IPS_SetEventActive(\$id, true); else IPS_SetEventActive(\$id, false);";
			 IPS_SetEventScript($eid, $script);
			 IPS_SetEventTrigger($eid, 1, $target);
			 IPS_SetEventActive($eid, true);
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
		
		$insIDs = (array) IPS_GetInstanceListByModuleID($GUID);
		return $insIDs;
	}
}
?>
