<?php
include_once("vlmc.php");
include_once("functions.php");
include_once("base.class.php");
include_once("positions.class.php");
include_once("races.class.php");
include_once('f_windAtPosition.php');

class users extends baseClass
{
  //var from db_users
  var $idusers,
    $boattype,
    $username,
    $password,
    $boatname,
    $color,
    $boatheading,
    $pilotmode,
    $pilotparameter,
    $engaged,
    $lastchange,
    $nwp,
    $userdeptime,
    $lastupdate,
    $loch,
    $country,
    $class,
    $targetlat,
    $targetlong,
    $targetandhdg,
    $mooringtime,
    $releasetime,
    $hidepos,
    $blocnote,
    $ipaddr,
    $pilototo,
    $theme;

  var $idowner = null;
    
  function initFromArray($row) {
    $this->idusers        = $row['idusers'];
    $this->boattype       = $row['boattype'];
    $this->username       = $row['username'];
    $this->password       = $row['password'];
    $this->boatname       = $row['boatname'];
    $this->color          = $row['color'];
    $this->boatheading    = $row['boatheading'];
    $this->pilotmode      = $row['pilotmode'];
    $this->pilotparameter = $row['pilotparameter'];
    $this->engaged        = $row['engaged'];
    $this->lastchange     = $row['lastchange'];
    $this->email          = $row['email'];
    $this->nwp            = $row['nextwaypoint'];
    $this->userdeptime    = $row['userdeptime'];
    $this->lastupdate     = $row['lastupdate'];
    $this->loch           = (float)$row['loch'];
    $this->country        = (strlen($row['country']) < 1 ) ? "00-UN" : $row['country'];
    $this->class          = $row['class'];
    $this->targetlat      = $row['targetlat'];
    $this->targetlong     = $row['targetlong'];
    $this->targetandhdg   = $row['targetandhdg'];
    $this->mooringtime    = $row['mooringtime'];
    $this->releasetime    = $row['releasetime'];
    $this->hidepos        = $row['hidepos'];
    $this->blocnote       = $row['blocnote'];
    if ( preg_match("/^http|:\/\/|script|language|<|>/i", $this->blocnote) ) {
        $this->blocnote="Some characters are not valid in your notepad. (Code inclusion, &gt;, &lt;, ...)";
    }
    $this->ipaddr         = $row['ipaddr'];
    $this->theme          = $row['theme'];
    if (is_null($this->theme) ) {
      $this->theme = 'default';
    }
  }

  function initFromId($forceMaster = False) {
    $id = $this->idusers;
    $query= "SELECT idusers, boattype, username, password,".
      " boatname, color, boatheading, pilotmode, pilotparameter,".
      " engaged, lastchange, email, nextwaypoint, userdeptime, " .
      " lastupdate, loch, country, class, targetlat,targetlong, targetandhdg, ".
      " mooringtime, releasetime, hidepos, blocnote, ipaddr, theme  FROM  users WHERE idusers = ".$id;

    if ($forceMaster) {
        $result = wrapper_mysql_db_query_writer($query) or die("\n FAILED !!\n");
    } else {
        $result = wrapper_mysql_db_query_reader($query) or die("\n FAILED !!\n");
    }
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    $this->initFromArray($row);
  }

  function users($id, $init = TRUE) {
    $this->idusers = intval($id);
    if ($init) {
      $this->initFromId();
    }
  }

  // end of init block


  //Wrapper
  function logUserEvent($logmsg) {
      logUserEvent($this->idusers , $this->engaged, $logmsg);
  }

  //Convenient bundle
  function logUserEventError($logmsg = null) {
      if (!is_null($logmsg)) $this->set_error($logmsg);
      $this->logUserEvent($this->error_string);
  }

  //update boatname and color
  function write()
  {
    //write everything in db
    $query = "UPDATE users SET `boatname` = '" . addslashes($this->boatname) . "'," .
      " `color` = '" . $this->color . "'," .
      " `theme` = '" . $this->theme . "'," .
      " `email` = '" . $this->email . "'," .
      " `country` = '" . $this->country . "'," .
      " `hidepos` =  " . $this->hidepos . "," .
      " `blocnote` = '" . mysql_real_escape_string( $this->blocnote) . "'" .
      " WHERE idusers = " . $this->idusers;
    wrapper_mysql_db_query_writer($query) or die("Query failed : " . mysql_error." ".$query);

    $this->logUserEvent("Update prefs.");

  }

  // locks a boat (so the engine won't run for it) for a time (seconds) from now
  function lockBoat($time)
  {
    $this->releasetime = time() + $time;
    $query = "UPDATE users SET releasetime = " . $this->releasetime .
      " WHERE idusers = " . $this->idusers;
    wrapper_mysql_db_query_writer($query) or die("Query failed : " . mysql_error." ".$query);
  }

  // Check pilote auto returns true if an action was done, else false
  function pilototoCheck() {
    $flag_pilototo=false;

    $now=time();
    // lookup for a task to do
    $query = "SELECT `taskid`, `pilotmode`, `pilotparameter` FROM `auto_pilot` WHERE `status`='". 
      PILOTOTO_PENDING . "' AND `idusers`=".$this->idusers." AND `time`<=".$now;
    $result = wrapper_mysql_db_query_reader($query) or die("Query failed : " . mysql_error." ".$query);

    while ( $row = mysql_fetch_array($result, MYSQL_ASSOC) ) {
        // Execute the task
        $PIM=$row['pilotmode'];
        if ( $PIM == 0 OR $PIM > MAX_PILOTMODE ) $flag_err=true;
        $this->pilotmode=$PIM;

        $PIP=$row['pilotparameter'];

        $logmsg = sprintf( "Executing task %d, PIM=%d, PIP=%s... ** ", $row['taskid'], $PIM, $PIP);
        printf("** AUTO_PILOT : $logmsg");
        $query="UPDATE users SET pilotmode=$PIM ";

        switch ($PIM) {
            case PILOTMODE_HEADING:
                // Setup the userclass for immediate use
                $this->boatheading=$PIP;
                $query .= ", boatheading=$PIP ";
              	break;
            case PILOTMODE_WINDANGLE:
                // Setup the userclass for immediate use
                $this->pilotparameter=$PIP;
                $query .= ", pilotparameter=$PIP ";
              	break;
            case PILOTMODE_ORTHODROMIC:
            case PILOTMODE_BESTVMG:
            case PILOTMODE_VBVMG:
                if ( strlen($PIP) != 0 && $PIP != "0" ) {
                    $values = explode("@", $PIP);
                    $Coords = explode(",", $values[0]);
		    $tlat   = floatval($Coords[0]);
		    $tlon   = floatval($Coords[1]);
		    // FIXME sanity check
                    $query .= ", targetlat=$tlat, targetlong=$tlon ";
                    if ( isset($values[1]) && $values[1] >= 0 ) {
                        $query .= ", targetandhdg=" . $values[1] ;
                        // Setup the userclass for immediate use
                        $this->targetandhdg = $values[1];
                    } else {
                        $query .= ", targetandhdg=-1 ";
                        // Setup the userclass for immediate use
                        $this->targetandhdg = -1;
                    }

                    echo $query;
                    // Setup the userclass for immediate use
                    $this->targetlat  = $tlat;
                    $this->targetlong = $tlon;
                }
                break;
        }
        // Don't forget to add the where clause... and execute the query
        $query .= " WHERE idusers=$this->idusers;";
        wrapper_mysql_db_query_writer($query); //or die("Query failed : " . mysql_error." ".$query);
        $this->logUserEvent($logmsg);

        // Mark the task as DONE
        $query = "UPDATE auto_pilot SET status = '" . PILOTOTO_DONE . "' WHERE taskid = ".$row['taskid'].";";
        wrapper_mysql_db_query_writer($query); //or die("Query failed : " . mysql_error." ".$query);

        // Purge old tasks
        $this->pilototoPurge();

        $flag_pilototo=true;
    }

    return ($flag_pilototo);
}

  // Give the number of pilototo tasks in the given Status (pending by default)
  function pilototoCountTasks($status = PILOTOTO_PENDING)
  {
    // lookup for a task to do
    $query = "SELECT count(*) NumTasks FROM auto_pilot
     WHERE idusers = $this->idusers
       AND status = '" . $status . "'";
    $result = wrapper_mysql_db_query_reader($query) or die("Query failed : " . mysql_error." ".$query);
    //echo $query;

    if ( $row = mysql_fetch_array($result, MYSQL_ASSOC) ) {
      $numRows=$row['NumTasks'];
    } else {
      $numRows=0;
    }

    return($numRows);

  }

  /* List the pilototo orders
    Update $this->pilototo array, with rows
      TID = taskid
      TTS = time (Task TimeStamp)
      PIM = pilotmode
      PIP = pilotparameter
      STS = StaTuS
    This should be checked against PILOTOTO_MAX_EVENTS
  */
  function pilototoList($forcemaster = False) {
      $now=time();
      $this->pilototo=array();
      // lookup for a task to do
      $query = "SELECT taskid as TID, time as TTS, pilotmode as PIM, pilotparameter as PIP, status as STS ".
               "FROM auto_pilot WHERE idusers = $this->idusers ORDER by TTS ASC";
      if ($forcemaster) { // Special case is needed because of the update delay of the slaves
          $result = wrapper_mysql_db_query_writer($query) or die("Query failed : " . mysql_error." ".$query);
      } else {
          $result = wrapper_mysql_db_query_reader($query) or die("Query failed : " . mysql_error." ".$query);
      }

      while ( $row = mysql_fetch_array($result, MYSQL_ASSOC) ) {
          array_push ($this->pilototo, $row);
      }
      return(0);
  }
  
  // Delete a task from pilototo
  function pilototoDelete($taskid) {
      $logmsg = "pilototoDelete : taskid=$taskid";
      $query = "DELETE FROM `auto_pilot` WHERE `idusers` = " .$this->idusers. " AND `taskid` = $taskid";

      $result = wrapper_mysql_db_query_writer($query);
      if(!($result)) {
          //Error d'accès sql ?
          $this->set_error_with_mysql_query($query);
          $this->logUserEventError($logmsg);
          return False;
      } else if (($numrows = mysql_affected_rows($GLOBALS['masterdblink'])) != 1) {
          $this->set_error("ERROR : $numrows lines updated !!!");
          $this->logUserEventError($logmsg);
          return False;        
      } else {
          $this->logUserEvent($logmsg);
          return True;
      }
  }

  // Delete Old Tasks from auto_pilot
  /**
   * if (seconds) is 0 => delete all tasks for this user
   *    else delete tasks older than (seconds) seconds
   */

  function pilototoPurge($seconds = PILOTOTO_KEEP) {
      // lookup for a task to do
      $query = "DELETE FROM `auto_pilot` WHERE `idusers` = ".$this->idusers;
      $logmsg = "Deleting all pilototo tasks";
      if ( $seconds !== 0 ) {
          $timestamp = time() - $seconds;
          $query .= " AND time   <= $timestamp";
          $logmsg .= " before $timestamp";
      }
  
      if ($result = wrapper_mysql_db_query_writer($query)) {
          $this->logUserEvent($logmsg);
          return True;
      } else {
          $this->set_error_with_mysql_query($query);
          $this->logUserEventError("FAILED : ".$logmsg);
          return False;
      }
  }

  // Add a task to Pilototo
  function pilototoAdd($time, $pim, $pip) {
      $logmsg = "PilotoAdd : (time : $time, pim : $pim, pip : $pip)";
      //checking parameter : FIXME there is no policy for type checking... (where and when)
      if (!is_int($time) or ($time <= $this->lastupdate)) {
          $this->set_error("FAILED : time is in the past or not int");
          $this->logUserEventError($logmsg);
          return False;
      }
      if (!is_int($pim)) {
          $this->set_error("FAILED : pim should be int");
          $this->logUserEventError($logmsg);
          return False;
      }

      //Counting tasks
      $query = "SELECT count(*) as nb_tasks FROM `auto_pilot` WHERE `idusers`=".$this->idusers;
      $result = wrapper_mysql_db_query_reader($query);
      if (!$result) {
          $this->set_error("FAILED : Error when couting tasks");
          $this->set_error_with_mysql_query($query);
          $this->logUserEventError($logmsg);
          return False;
      }
      
      //Checking max events
      $row = mysql_fetch_assoc($result);
      if ( $row['nb_tasks'] >= PILOTOTO_MAX_EVENTS) {
          $this->set_error("pilototoAdd : PILOTOTO_MAX_EVENTS reached");
          $this->logUserEventError($logmsg);
          return False;
      }

      //inserting task
      $query = "INSERT INTO `auto_pilot` ( time, idusers, pilotmode, pilotparameter, status) " .
               "VALUES ( " .$time. ", " .$this->idusers. ", '" .$pim. "', '" .$pip. "', '" .PILOTOTO_PENDING. "');";
               
      $logmsg = "PilotoAdd : (time : $time, pim : $pim, pip : $pip)";
      
      if ($result = wrapper_mysql_db_query_writer($query)) {
          $this->logUserEvent($logmsg);
          return True;
      } else {
          //Error d'accès sql ?
          $this->set_error_with_mysql_query($query);
          $this->logUserEventError($logmsg);
          return False;
      }

  }

  // Update a task of the pilototo
  function pilototoUpdate($taskid, $time, $pim, $pip)
  {
      $time = intval($time);
      $logmsg = "Update pilototo task $taskid : time=$time, pim=$pim, pip=$pip";
      if ($time < time()) {
          $this->set_error("FAILED : time < now()");
          $this->logUserEventError($logmsg);
          return False;
      }
      // lookup for a task to do
      $query = "UPDATE `auto_pilot` SET `time`=$time, ".
               "`pilotmode` = $pim, ".
               "`pilotparameter` = '" . $pip . "', ".
               "`status` = '" .PILOTOTO_PENDING . "' ".
               "WHERE `idusers` = " .$this->idusers. " AND `taskid` = $taskid";

      $result = wrapper_mysql_db_query_writer($query);
      if (!$result) {
          $this->set_error_with_mysql_query($query);
          $this->logUserEventError($logmsg);
          return False;
      } else if (($numrows = mysql_affected_rows($GLOBALS['masterdblink'])) > 1) {
          $this->set_error("ERROR: $numrows lines updated !!!");
          $this->logUserEventError($logmsg);
          return False;
      } else {
          $this->logUserEvent($logmsg);
          return True;
      }

  }

  function htmlFlagImg() {
      //Convenient mapping
      return htmlFlagImg($this->country);
  }

  function htmlBoattypeLink() {
      //Convenient mapping
      return htmlBoattypeLink($this->boattype);
  }

  function htmlIdusersUsernameLink() {
      //This function is also in the race class
      return htmlIdusersUsernameLink($this->country, $this->color, $this->idusers, $this->boatname, $this->username);
  }

  function htmlIdusers() {
      $ret = "<a href=\"palmares.php?type=user&amp;idusers=".$this->idusers."\">#".$this->idusers."</a>";
      return $ret;
  }

  function getOwnerId() {
      if (!is_null($this->idowner)) return $this->idowner;
      $query = "SELECT idplayers FROM playerstousers WHERE idusers =".$this->idusers." AND linktype = ".PU_FLAG_OWNER;
      $res = $this->queryRead($query);
      if (!$res || mysql_num_rows($res) == 0) {
          $this->idowner = 0;
      } else {
          $row = mysql_fetch_assoc($res);
          $this->idowner = $row['idplayers'];
      }
      return $this->idowner;
  }
  
  function setOwnerId($idowner) {
      if (getPlayerObject($idowner)->hasMaxBoats() ) {
          $this->users->set_error("Player reached max boats");
          return False;
      }

      if ($this->setRelationship($idowner, PU_FLAG_OWNER)) {
          $this->idowner = $idowner;
          return True;
      }          
      return False;
  }

  function setRelationship($idplayer, $relationship, $done_by_idplayer = null) {
      $idplayer = intval($idplayer);
      $playername = getPlayerObject($idplayer)->playername;
      if (is_null($done_by_idplayer)) $done_by_idplayer = $idplayer;
      $relationship = intval($relationship);
      if ($idplayer > 0) {
          $query = "REPLACE playerstousers SET idusers = ".$this->idusers.", idplayers = ".$idplayer.", linktype = ".$relationship;
          if ($this->queryWrite($query)) {
              switch($relationship) {
                  case PU_FLAG_OWNER :
                      $logmsg = "Took ownership of this boat.";
                      break;
                  case PU_FLAG_BOATSIT :
                      $logmsg = "Granted $playername @$idplayer as boatsitter of this boat.";
                      break;
                  default :
                      $logmsg = "Attached boat to $playername @$idplayer with linktype = ".$relationship;
              }
              logPlayerEvent($done_by_idplayer, $this->idusers, $this->engaged, $logmsg);
              return True;
          }
      }
      return False;
  }

  function removeRelationship($idplayer, $relationship, $done_by_idplayer = null) {
      $idplayer = intval($idplayer);
      if (is_null($done_by_idplayer)) $done_by_idplayer = $idplayer;
      $relationship = intval($relationship);
      if ($idplayer > 0) {
          $query = "DELETE FROM playerstousers WHERE idusers = ".$this->idusers." AND idplayers = ".$idplayer." AND linktype = ".$relationship;
          if ($this->queryWrite($query)) {
              switch($relationship) {
                  //FIXME : translation !
                  case PU_FLAG_OWNER :
                      $logmsg = "Player @$idplayer no longer owner of this boat.";
                      break;
                  case PU_FLAG_BOATSIT :
                      $logmsg = "Player @$idplayer no longer boatsitter of this boat.";
                      break;
                  default :
                      $logmsg = "Boat and player @$idplayer not linked anymore  with linktype = ".$relationship;
              }
              logPlayerEvent($done_by_idplayer, $this->idusers, $this->engaged, $logmsg);
              return True;
          }
      }
      return False;
  }

  function hasTrackHidden() {
      return False; //#658 - substr($this->color,0,1) == "-";
  }
  
  function isEngaged() {
      return $this->engaged > 0;
  }
  
  function computeAvg() {
      $dt = time() - $this->userdeptime;
      if ($dt != 0) {
          return(3600*$this->loch/$dt);
      } else {
          return(0);
      }
  }

}



class fullUsers
{
  var $users, //a user object
    //var deduced from others
    $lastPositions,     //a positions object
    $hours,
    $wspeed, $wheading,
    $boatanglewithwind, $boatspeed,
    $anteLastPositions, //another positions obj
    $races,             //include a race object
    $loxoangletoend, $orthoangletoend,
    $distancefromend, $nwp,
    $VMG, $VMGortho,
    $LongNM, $LatNM,
    $preferences;

  function fullUsers($id, $origuser = NULL, $origrace = NULL, $north = 80000, $south = -80000, $west = -180000, $east = 180000, $age = MAX_DURATION)
  {
    $now = time();

    if (is_null($origuser)) {
      $this->users = getUserObject($id);
      if (is_null($this->users)) return;
    } else {
      $this->users = &$origuser;
    }

    // if boat not engage in a race, nothing else to do ....
    if ($this->users->engaged == 0) return;

    $querypos = "SELECT `time`, `long`, `lat`, `idusers` , `race` ".
      " FROM positions WHERE idusers=".$this->users->idusers.
      " AND race = ".$this->users->engaged.
      " ORDER BY `time` DESC LIMIT 2";
    $result = wrapper_mysql_db_query_reader($querypos);
    $this->lastPositions     = new positions;
    $this->anteLastPositions = new positions;
    
    if ($result) {
      $row = mysql_fetch_array($result, MYSQL_ASSOC);
      if ($row) {
	$this->lastPositions->init($row);
	$row = mysql_fetch_array($result, MYSQL_ASSOC);
	if ($row) {
	  $this->anteLastPositions->init($row);
	}
      }
    }
    // inherit of nwp
    $this->nwp = &$this->users->nwp;

    if ($this->lastPositions->idusers == 0 ) {
      //if object is empty
      //that shouldnot happen if base is written automaticaly
      //but it's dangerous
      //write a default position
      
      //echo "writing default positions ".$this->users->idusers."\n";
      $this->lastPositions->writeDefaultPositions($this->users->idusers, 
						  $this->users->engaged);
    }

    // this->hours (temps depuis la dernière VAC)
    if ( $this->users->userdeptime == -1 ) {
      $time = $now;
      $this->hours = 0;
    } else {
      $time = $this->lastPositions->time;
      $this->hours = ($now - $time )/3600 ;  //everything is in GMT
    }

    if ($origrace == NULL) {
      $this->races = new races($this->users->engaged);
    } else {
      $this->races = &$origrace->races;
    }

    // windAtPosition returns a small array : (wspeed, wheading);
    // see functions.php
    $wind = windAtPosition($this->lastPositions->lat, 
			   $this->lastPositions->long, 0);
    $this->wspeed = $wind['speed'];
    $this->wheading = $wind['windangle'];

    //find the angle boat/wind
    $this->boatanglewithwind = angleDifference($this->users->boatheading,
                                               $this->wheading) ;
    //echo "\n**angleDifference ( " . $this->users->boatheading . " and " . $this->wheading .") is ".$this->boatanglewithwind . "**";

    //find boatspeed
    //echo "calling findboatspeed with ".$this->boatanglewithwind." ". $this->wspeed." ".  $this->users->boattype;
    $this->boatspeed =  findboatspeed(abs($this->boatanglewithwind),
                                      $this->wspeed,
                                      $this->users->boattype);

    // Find the best coordinates to cross the nextwaypoint (LatNM & LongNM)
    /*
       Since 2007-October-10,
       these LatNM & LongNM are a Waypoint given by the user (if not 0/0)
    */
    if ( $this->users->targetlat == 0 && $this->users->targetlong == 0 ) {
      //echo "*Race WP*";
      $rc = $this->bestWayToWaypoint($this->getCurrentClassificationWaypointIdx());
    } else {
      //echo "*User WP*";
      $this->LatNM = $this->users->targetlat*1000;
      $this->LongNM = $this->users->targetlong*1000;
    }

    $this->distancefromend = ortho($this->lastPositions->lat, $this->lastPositions->long,
           $this->LatNM, $this->LongNM);

    $this->loxoangletoend = $this->loxodromicHeading();
    $this->orthoangletoend = $this->orthodromicHeading();

    $this->VMG =
      VMG( $this->lastPositions->long, $this->lastPositions->lat,
           $this->LongNM, $this->LatNM,
           $this->users->boatheading, $this->boatspeed, 0);

    $this->VMGortho =
      VMGortho($this->lastPositions->long, $this->lastPositions->lat,
               $this->users->boatheading, $this->boatspeed, $this->orthoangletoend);

    //    print_r($this);

  }

  function updateNM() {
    if ( $this->users->targetlat == 0 && $this->users->targetlong == 0 ) {
      //echo "*Race WP*";
      $rc = $this->bestWayToWaypoint($this->getCurrentClassificationWaypointIdx());
    } else {
      //echo "*User WP*";
      $this->LatNM = $this->users->targetlat*1000;
      $this->LongNM = $this->users->targetlong*1000;
    }
  }

  function feedPrefs() {
      if (!isset($this->preferences)) {
          $query_pref = "SELECT pref_name, pref_value FROM user_prefs".
                        " WHERE idusers = ".$this->users->idusers;
          $result_pref = wrapper_mysql_db_query_reader($query_pref) or die($query_pref);
          $this->preferences = array();
          while( $row = mysql_fetch_array($result_pref, MYSQL_ASSOC) ) {
              $this->preferences[$row['pref_name']] = $row['pref_value'];
          }
          //Special case for "old" prefs
          $this->preferences['blocnote'] = $this->users->blocnote;
          $this->preferences['color'] = $this->users->color;
          $this->preferences['theme'] = $this->users->theme;
          $this->preferences['country'] = $this->users->country;
          $this->preferences['boatname'] = $this->users->boatname;
      }
  }
  
  function getMyPref($pref_name) {
      $this->feedPrefs();
      if (array_key_exists($pref_name, $this->preferences)) {
        return $this->preferences[$pref_name];
      } else {
          return NOTSET;
      }
  }

  //====================================================================================
  // This function gives the lat ant long where it seems the best to cross next waypoint
  //====================================================================================
  function bestWayToWaypoint($wp)
  {
    $lat_xing   = new doublep();
    $long_xing  = new doublep();
    $xing_ratio = new doublep();
    
    // Get coords of the nextwaypoint
    $nextwaypoint = $this->races->giveWPCoordinates($wp);
    
    // Get the best crossing point
    if (defined('MOTEUR')) {
      $xing_dist = VLM_distance_to_wp_ratio_xing($this->lastPositions->lat, $this->lastPositions->long,
						 $nextwaypoint, $lat_xing, $long_xing, $xing_ratio);
    } else {
      $xing_dist = VLM_distance_to_line_ratio_xing($this->lastPositions->lat, $this->lastPositions->long,
						   $nextwaypoint['latitude1'], $nextwaypoint['longitude1'],
						   $nextwaypoint['latitude2'], $nextwaypoint['longitude2'],
						   $lat_xing, $long_xing, $xing_ratio);
    }
    
    // printf ("Lat=%f, Long=%f\n", doublep_value($lat_xing) / 1000.0, doublep_value($long_xing) / 1000.0);
    $this->LatNM  = doublep_value($lat_xing);
    $this->LongNM = doublep_value($long_xing);
  }
  


  // this function will update the userdeptime in the users table
  function updateDepTime($time)
  {
    //TODO write a positions->deletepositions that will be called here for every positions
    //delete old positions from database
    //printf ("Time = %d\n",$time);
    $query_deptime = "UPDATE users SET userdeptime = " . $time . " WHERE idusers = ". $this->users->idusers  ;
    //echo ( "Query failed : " . mysql_error." ".$query_deptime );
    wrapper_mysql_db_query_writer($query_deptime) or die ( "Query failed : " . mysql_error." ".$query_deptime );
    $this->users->userdeptime = $time;
  }

  //this function will delete all the positions of the boat for this race
  function deletePositions($idraces)
  {
    //TODO write a positions->deletepositions that will be called here for every positions
    //delete old positions from database
    $query65 = "DELETE FROM positions WHERE idusers = ". $this->users->idusers  .
      " AND  race = " . $idraces;
    wrapper_mysql_db_query_writer($query65);// or die("Query failed : " . mysql_error." ".$query65);
  }

  // delete entries in the races_loch table for this user
  // we are not filtering on the race id, as this is a transient table, so
  // no need to keep values for finished races
  function deleteRaceLoch() {
    $query = "DELETE FROM races_loch WHERE idusers=".$this->users->idusers;
    wrapper_mysql_db_query_writer($query);
  }

  function updateAngles($write = 1)
  {
    switch ($this->users->pilotmode) {
    case PILOTMODE_WINDANGLE:
      //update boatheading
      $this->users->boatheading = (($this->wheading+180) + $this->users->pilotparameter) ;
      
      while ( $this->users->boatheading > 360 ) $this->users->boatheading-=360;
      while ( $this->users->boatheading < 0 ) $this->users->boatheading+=360;
      break;
    case PILOTMODE_ORTHODROMIC:
      //update boatheading
      $this->users->boatheading = $this->orthodromicHeading();
      break;
    case PILOTMODE_BESTVMG:
      $vlmc_heading = new doublep();
      $vlmc_vmg = new doublep();
      if (defined('MOTEUR')) {
	shm_lock_sem_construct_grib(1);
	VLM_best_vmg($this->lastPositions->lat,
		     $this->lastPositions->long,
		     $this->LatNM, $this->LongNM,
		     $this->users->boattype,
		     $vlmc_heading, $vlmc_vmg);
	shm_unlock_sem_destroy_grib(1);
      } else { // in regular mode, create and fill context first
	$temp_vlmc_context = new vlmc_context();
	shm_lock_sem_construct_polar_context($temp_vlmc_context, 1);
	shm_lock_sem_construct_grib_context($temp_vlmc_context, 1);
	VLM_best_vmg_context($temp_vlmc_context, $this->lastPositions->lat,
			     $this->lastPositions->long,
			     $this->LatNM, $this->LongNM,
			     $this->users->boattype,
			     $vlmc_heading, $vlmc_vmg);
	shm_unlock_sem_destroy_grib_context($temp_vlmc_context, 1);
	shm_unlock_sem_destroy_polar_context($temp_vlmc_context, 1);
      }

      $this->users->boatheading = doublep_value($vlmc_heading);
      $this->VMG = doublep_value($vlmc_vmg);

      //	  echo "Debug: Lat   = ".$this->lastPositions->lat;
      //	  echo "Debug: Lon   = ".$this->lastPositions->long;
      //	  echo "Debug: WPLat = ".$this->LatNM;
      //	  echo "Debug: WPLon = ".$this->LongNM;
      //	  echo "Debug: Type  = ".$this->users->boattype;
      //	  echo "Debug: HDG   = ".$this->users->boatheading;
      //	  echo "Debug: VMG   = ".$this->VMG;
      break;
    case PILOTMODE_VBVMG:
      $vlmc_heading = new doublep();
      $vlmc_vmg = new doublep();
      if (defined('MOTEUR')) {
	shm_lock_sem_construct_grib(1);
	VLM_vbvmg($this->lastPositions->lat,
		  $this->lastPositions->long,
		  $this->LatNM, $this->LongNM,
		  $this->users->boattype,
		  $vlmc_heading, $vlmc_vmg);
	shm_unlock_sem_destroy_grib(1);
      } else { // in regular mode, create and fill context first
	$temp_vlmc_context = new vlmc_context();
	shm_lock_sem_construct_polar_context($temp_vlmc_context, 1);
	shm_lock_sem_construct_grib_context($temp_vlmc_context, 1);
	VLM_vbvmg_context($temp_vlmc_context, $this->lastPositions->lat,
			  $this->lastPositions->long,
			  $this->LatNM, $this->LongNM,
			  $this->users->boattype,
			  $vlmc_heading, $vlmc_vmg);
	shm_unlock_sem_destroy_grib_context($temp_vlmc_context, 1);
	shm_unlock_sem_destroy_polar_context($temp_vlmc_context, 1);
      }

      $this->users->boatheading = doublep_value($vlmc_heading);
      $this->VMG = doublep_value($vlmc_vmg);

      //	  echo "Debug: Lat   = ".$this->lastPositions->lat;
      //	  echo "Debug: Lon   = ".$this->lastPositions->long;
      //	  echo "Debug: WPLat = ".$this->LatNM;
      //	  echo "Debug: WPLon = ".$this->LongNM;
      //	  echo "Debug: Type  = ".$this->users->boattype;
      //	  echo "Debug: HDG   = ".$this->users->boatheading;
      //	  echo "Debug: VMG   = ".$this->VMG;
      break;
    case PILOTMODE_BESTSPEED:
      // FIXME if kept, needs to be redone in vlm-c
      $Hdg=0; $bestHdg=0;
      $Spd=-1 ;$bestSpd=-1;
      while ( $Hdg <= 359 ) {
	$Spd = findboatspeed( angleDifference($Hdg, $this->wheading),
			      $this->wspeed,
			      $this->users->boattype);
	if ( $Spd >= $bestSpd ) {
	  $bestHdg=$Hdg;
	  $bestSpd=$Spd;
	}
	$Hdg+=1;
	//echo "DEBUG Spd=$Spd, H=$Hdg \n";
      }

      // On se refait un petit calcul avec un pas de 0.1 autour du cap "au degré près".
      for ( $Hdg=$bestHdg-1;$Hdg<$bestHdg+1;$Hdg+=0.1 ) {
	$Spd = findboatspeed( angleDifference($Hdg, $this->wheading),
			      $this->wspeed,
			      $this->users->boattype);
	if ( $Spd > $bestSpd ) {
	  $bestHdg=$Hdg;
	  $bestSpd=$Spd;
	}
	//echo "DEBUG Spd=$Spd, H=$Hdg \n";
      }

      $this->users->boatheading = $bestHdg;
      break;
    }

    if ($write == 1 && ($this->users->pilotmode != PILOTMODE_HEADING)) {
      $query1 = "UPDATE users SET boatheading =". $this->users->boatheading
	." WHERE idusers =".$this->users->idusers;
      $result1 = wrapper_mysql_db_query_writer($query1);
    }
    
    //find the angle boat/wind
    $this->boatanglewithwind = angleDifference($this->users->boatheading,
                                               $this->wheading) ;

    //find boatspeed
    $this->boatspeed =  findboatspeed($this->boatanglewithwind,
                                      $this->wspeed,
                                      $this->users->boattype);
    
  }

  function abandonWpAndTarget() {

      //No logging, only called by engine

      $query = "UPDATE `users` SET `targetandhdg` = -1, " ;

      // If targetandhdg is between 0 and 360
      if ( $this->users->targetandhdg>= 0 and $this->users->targetandhdg<=360) {
          // New heading will become this heading, and pilotmode is set to PILOTMODE_HEADING
          $this->users->pilotmode      = PILOTMODE_HEADING;
          $this->users->pilotparameter = $this->users->targetandhdg;
          $this->users->boatheading    = $this->users->targetandhdg;

          $query .=  " `pilotmode` = " . $this->users->pilotmode      . " ," ;
          $query .=  " `pilotparameter` = " . $this->users->pilotparameter . " ," ;
          $query .=  " `boatheading` = " . $this->users->boatheading    . " ," ;
      }  else  {
          // On ne touche pas au pilotmode (c'est peut-être Ortho ou BestVMG ou VBMG)
          // Mais il faut remettre à jour LatNM et LongNM
	$rc = $this->bestWayToWaypoint($this->getCurrentClassificationWaypointIdx());
      }
      $this->users->targetandhdg = -1;
      $this->users->targetlat    = 0;
      $this->users->targetlong   = 0;

      $query .= " `targetlat` = " . $this->users->targetlat      . " ," ;
      $query .= " `targetlong` = " . $this->users->targetlong     . " ," ;
      $query .= " `lastchange` = " .time(). " WHERE `idusers` = " . $this->users->idusers;

      return wrapper_mysql_db_query_writer($query);
  }

  /* update the target
   *  - lat / long
   *  - hdg (-1 if not valid)
   */
  function updateTarget($lat, $long, $hdg) {

      if ($this->users->targetlat == $lat && $this->users->targetlong == $long && $this->users->targetandhdg == $hdg) return True;

      //Update targetlat
      if ( is_numeric($lat) && abs($lat)<90 ) {
          $this->users->targetlat = $lat;
      } else {
          $this->users->targetlat = 0;
      }

      //Update targetlong
      if ( is_numeric($long) ) {
          while ( $long >180 ) $long-=360;
          while ( $long <=-180 ) $long+=360;
          $this->users->targetlong = $long;
      } else {
          $this->users->targetlong = 0;
      }

      //Update targetandhdg
      if ( is_numeric($hdg) && $hdg<=360 && $hdg>=0) {
          $this->users->targetandhdg = $hdg;
      } else {
          $this->users->targetandhdg=-1;
      }

      $timestamp=time(); //Impact si différence au niveau temps des serveurs...
      $query = "UPDATE `users` SET " .
                "`targetlat`  = " . $this->users->targetlat .
                " , `targetlong` = " . $this->users->targetlong .
                " , `targetandhdg` = " . $this->users->targetandhdg .
                " , `lastchange` = ". $timestamp .
                " , `ipaddr` = '". $_SESSION['IP'] . "'" .
                " WHERE `idusers` = ".$this->users->idusers;

      $logmsg = "Update Target (lat=" . $this->users->targetlat. ", lon=" . $this->users->targetlong. ", @wph=" . $this->users->targetandhdg. ")" ;
      if ($result = wrapper_mysql_db_query_writer($query)) {
          $this->users->logUserEvent($logmsg);
	  $this->updateNM();
          $this->updateAngles(1);
          return True;
      } else {
          //Error d'accès sql ?
          $this->users->logUserEvent("FAILED : ".$logmsg);
          $this->users->set_error($logmsg);
          $this->users->set_error_with_mysql_query($query);
          return False;
      }

  }


  //remove player from races
  function removeFromRaces()
  {
    $queryhistopositions = "INSERT INTO histpos SELECT * FROM positions WHERE idusers=" . 
      $this->users->idusers . " AND race=" . $this->users->engaged;
    wrapper_mysql_db_query_writer($queryhistopositions);
    //echo "QH = $queryhistopositions" . "\n";

    $querypurgepositions = "DELETE FROM positions WHERE idusers=" . $this->users->idusers . 
      " AND race=" . $this->users->engaged;
    wrapper_mysql_db_query_writer($querypurgepositions);
    //echo "QP = $querypurgepositions" . "\n";

    $querypurgeinvalidwps = "DELETE FROM waypoint_crossing WHERE validity=0 AND idusers=".
      $this->users->idusers." AND idraces=".$this->users->engaged ;
    wrapper_mysql_db_query_writer($querypurgeinvalidwps);
    //echo "QW = $querypurgeinvalidwps" . "\n";
    
    // And then, the most important...
    $this->subscribeToRaces(0);
  }

  function getNextWaypoint()
  {
    // Retourne -1 si il n'y a plus de waypoints (on a passé le dernier, donc la finish line)
    //     select wporder from races_waypoints where idraces=35 and wporder >1 ORDER BY wporder ASC LIMIT 1;
    if ($this->users->nwp < $this->races->getWPsCount()) {
      return ($this->users->nwp + 1);
    }
    return (-1);
  }

  function getPreviousWaypointIdx()
  {
    if ($this->users->nwp < 2) {
      return 0;
    }
    return ($this->users->nwp - 1);
  }
  
  function getCurrentClassificationWaypointIdx() {
    $raceWPs = $this->races->getWPs();
    $max_wp_idx = count($raceWPs);
    $wpidx = $this->users->nwp;
    if (is_null($wpidx)) $wpidx = 1;
    $cur_wp = $raceWPs[$wpidx];

    if (defined('MOTEUR')) {
        while(($cur_wp->type & (WP_ICE_GATE_N|WP_ICE_GATE_S)) != 0) {
            $wpidx++;
            $cur_wp = $raceWPs[$wpidx];
        }
    } else {
        while(($cur_wp['wpformat'] & (WP_ICE_GATE_N|WP_ICE_GATE_S)) != 0) {
            $wpidx++;
            $cur_wp = $raceWPs[$wpidx];
        }
    }
    return $wpidx;
  }

  function getCurrentClassificationWaypoint() {
    $this->races->giveWPCoordinates(getCurrentClassificationWaypointIdx());
  }
  
  // Function updateWaypoints
  function recordWaypointCrossing($xingtime, $validity = 1)
  {
    // Choix de "userdeptime"
    $udt = $this->users->userdeptime;

    $query = "REPLACE INTO waypoint_crossing " .
      " (idraces , idwaypoint, idusers , validity, time, userdeptime) " .
      " VALUES ( " . $this->users->engaged . ", " .
      $this->nwp . ", " .
      $this->users->idusers . ", " .
      $validity . ", ".
      $xingtime . ", ".
      $udt . ");"   ;

    wrapper_mysql_db_query_writer($query) ;//or die("Query failed : " . mysql_error." ".$query);
    if ($validity == 1) {
      $this->users->logUserEvent("Boat crossed WP " . $this->nwp . " in race : " . $this->users->engaged );
    } else {
      $this->users->logUserEvent("Invalid WP crossing " . $this->nwp . " in race : " . $this->users->engaged );
    }
  }

  // clear prior invalid waypoint crossing, if any
  function clearInvalidWaypointCrossing() 
  {
    $query = "DELETE FROM waypoint_crossing WHERE validity=0 AND idusers=".
      $this->users->idusers." AND idraces=".$this->users->engaged.
      " AND idwaypoint=".$this->nwp;
    wrapper_mysql_db_query_writer($query) ;//or die("Query failed : " . mysql_error." ".$query);
    $this->users->logUserEvent("Cleared invalid WP crossing " . $this->nwp . " in race : " . $this->users->engaged );
  }

  // clear prior valid waypoint crossing, if any
  function clearValidWaypointCrossing($all=true) 
  {
    $query = "DELETE FROM waypoint_crossing WHERE ".($all?"":" validity=1 AND")." idusers=".
      $this->users->idusers." AND idraces=".$this->users->engaged.
      " AND idwaypoint=".$this->nwp;
    wrapper_mysql_db_query_writer($query) ;//or die("Query failed : " . mysql_error." ".$query);
  }
  
  // check if there was an invalid crossing reported for that WP
  function checkInvalidWaypointCrossing()
  {
    $query = "SELECT count(*) AS nbinvalid FROM waypoint_crossing WHERE validity=0 AND idusers=".
      $this->users->idusers." AND idraces=".$this->users->engaged.
      " AND idwaypoint=".$this->nwp;
    $result = wrapper_mysql_db_query_reader($query) or die("Query failed : " . mysql_error." ".$query);
    $rowresult = mysql_fetch_array($result, MYSQL_ASSOC);
    return $rowresult['nbinvalid'];
  }
  
  function checkExistingCurrentWaypointCrossing() 
  {
    // we use userdeptime to ensure we are not taking a waypoint from a previous run
    // in the case of a permanent race.
    $query = "SELECT count(*) AS nbvalid FROM waypoint_crossing WHERE validity=1 AND idusers=".
      $this->users->idusers." AND idraces=".$this->users->engaged.
      " AND idwaypoint=".$this->nwp." AND userdeptime=".$this->users->userdeptime;
    $result = wrapper_mysql_db_query_reader($query) or die("Query failed : " . mysql_error." ".$query);
    $rowresult = mysql_fetch_array($result, MYSQL_ASSOC);
    return ($rowresult['nbvalid']>0);
  }

  // Function updateWaypoints
  function updateNextWaypoint()
  {
    // MAJ la table users pour prise en compte du prochain Waypoint
    $query = "UPDATE users SET nextwaypoint = " . $this->nwp .
      " WHERE idusers = " . $this->users->idusers;
    wrapper_mysql_db_query_writer($query); // or die("Query failed : " . mysql_error." ".$query);
    //printf ("Request USERS : %s\n" , $query);

    // MAJ la table races_ranking pour prise en compte du prochain Waypoint
    //$query = "UPDATE races_ranking SET nwp = " . $this->nwp . ", " .
    //         "                         dnm       = " . $this->distancefromend     . ", " .
    //       " WHERE idusers = " . $this->users->idusers .
    //       "   AND idraces = " . $this->users->engaged;
    //   wrapper_mysql_db_query($query);// or die("Query failed : " . mysql_error." ".$query);
    //  printf ("Request RACES_RANKING : %s\n" , $query);
  }

  function writeCurrentRanking ( $moved = 1 , $now = 0)
  {
    if ($now == 0) {
      $now = time();
    }
    // Record classification data
    //"        (idraces , idusers , nwp , dnm, latitude, longitude, last1h, last3h, last24h)  " .
    // ICI, on appelle vraiment la fonction bWTW, pour indiquer la bonne distance dans les classements
    // MAIS : bug du 10/10/2007 : elle modifie LatNM et LongNM... !
    //==> En attendant de faire plus élégant, on sauve/restore ces deux valeurs (pas beau, non... pas du tout)
    // =======================================================================================
    // On met à jour les colonnes lastupdate + lastchange et la colonne loch de la table users
    // =======================================================================================
    $query_update = "UPDATE users SET ";

    // On maj Lastchange uniquement pour les bateaux qui ne sont pas bout au vent.
    if ( $this->users->pilotmode != 2
         || ( $this->users->pilotmode ==2 && $this->users->pilotparameter != 0 )  ) {

      $query_update .= " lastchange = " . time() . "," ;
    }

    // Cumul du loch sauf si bout au vent...
    if ( $moved == 1 && ($this->anteLastPositions->time != 0))  {
      //FIXME : loch devrait être un DECIMAL
      $this->users->loch += $this->boatspeed*$this->hours;
      $query_update .= " loch = " . $this->users->loch . "," ;
    }

    // On décrémente HidePos si positif
    if ( $this->users->hidepos > 0 ) {
      $this->users->hidepos = $this->users->hidepos - 1 ;
      $query_update .= " hidepos = " .  $this->users->hidepos . "," ;
    }

    $query_update .= " lastupdate = " . time() ;
    $query_update .= " WHERE idusers  = " . $this->users->idusers ;
    wrapper_mysql_db_query_writer($query_update);// or die("Query failed : " . mysql_error." ".$query_ranking);

    // =======================================================================================
    // En cas de blackout, on a fini.
    // =======================================================================================
    if ( $this->races->bobegin < $now && $now < $this->races->boend ) {
      if ( $this->users->idusers > 0 ) {
        printf ("*** Blackout ACTIVE ***\n");
      }
      return(0);
    }


    // =======================================================================================
    // En cas de StealthPlay, on a fini aussi.
    // =======================================================================================
    if ( $this->users->hidepos > 0 ) {
      printf ("*** StealthPlay ACTIVE (%d) ***\n", $this->users->hidepos);
      return(0);
    }


    // =======================================================================================
    // Si on est encore là : Calculs et mise à jour des classements (table races_ranking)
    // =======================================================================================
    $sauvlong=$this->LongNM;
    $sauvlat=$this->LatNM;
    //==> On restore quand on a plus besoin des "vraies valeurs"...

    $rc = $this->bestWayToWaypoint($this->getCurrentClassificationWaypointIdx());
    $this->distancefromend = ortho($this->lastPositions->lat, $this->lastPositions->long,
           $this->LatNM, $this->LongNM);


    // 1 : corrected, 0 : not corrected
    $dist = $this->distRecords(24*3600);
    if ( $this->users->loch > $dist[1] ) {
      $last24h = $dist[1];
    } else {
      $last24h = $dist[0];
    }
    if ( $last24h > 24 * MAX_SPEED_FOR_RANKING ) $last24h = 0;

    $dist = $this->distRecords(3*3600);
    if ( $this->users->loch > $dist[1] ) {
      $last3h = $dist[1];
    } else {
      $last3h = $dist[0];
    }
    if ( $last3h > 3 * MAX_SPEED_FOR_RANKING ) $last3h = 0;

    $dist = $this->distRecords(3600);
    if ( $this->users->loch > $dist[1] ) {
      $last1h = $dist[1];
    } else {
      $last1h = $dist[0];
    }
    if ( $last1h > MAX_SPEED_FOR_RANKING ) $last1h = 0;

    $query_ranking = "UPDATE races_ranking SET " .
      "nwp       = " . $this->nwp                 . ", " .
      "dnm       = " . $this->distancefromend     . ", " .
      "nmlat     = " . $this->LatNM               . ", " .
      "nmlong    = " . $this->LongNM              . ", " .
      "latitude  = " . $this->lastPositions->lat  . ", " .
      "longitude = " . $this->lastPositions->long . ", " .
      "loch      = " . $this->users->loch         . ", " .
      "last1h    = " . $last1h                    . ", " .
      "last3h    = " . $last3h                    . ", " .
      "last24h   = " . $last24h                   .
      " WHERE idraces = " . $this->users->engaged .
      " AND idusers   = " . $this->users->idusers ;

    wrapper_mysql_db_query_writer($query_ranking);// or die("Query failed : " . mysql_error." ".$query_ranking);
    //printf ("Query : %s\n", $query_ranking);


    //==> On restore là, maintenant...
    $this->LongNM=$sauvlong;
    $this->LatNM=$sauvlat;

    if ( $this->users->idusers > 0 ) {
      printf ("\n\t\t*** NWP=%d, DNM=%f ", $this->nwp, $this->distancefromend);
      printf ("\n\t\t*** ranking updated.\n");
    }

  }

  function deleteCurrentRanking()
  {
    // Classification data is to be deleted when a player gets out of a race
    $query_ranking = "DELETE from races_ranking " .
      " WHERE idraces  = " . $this->users->engaged .
      "  AND  idusers  = " . $this->users->idusers ;

    wrapper_mysql_db_query_writer($query_ranking); 

  }
  
  function setPref($key, $value) {
      //FIXME: this is a duplicate of setUserPref. Putting here to catch the mysql error if needed
      if (!in_array($key, explode(',', USER_PREF_ALLOWED))) {
          $this->users->set_error("UNALLOWED PREFS KEY");
          return False;
      }
      
      $value = strip_tags(trim($value));
      //FIXME: we should check prefs values better !
      switch($key) {
          //numeric 
          case "maparea":
          case "mapMaille" :
          case "mapX" :
          case "mapY" :
          case "mapAge" :
          case "mapEstime" :
              if (!is_numeric($value)) {
                  $this->users->set_error("Value of $key not numeric");
                  return False;
              }
          case "blocnote" :
          case "boatname" :
          //more cases
              //Should check here that UTF8 is valid
          default :
      }

      $value = mysql_real_escape_string($value);
      
      //FIXME: special cases : notepad and others should be a boat/user prefs but are not but will be
      if (in_array($key, Array("blocnote", "color", "theme", "country", "boatname"))) {
          $query_pref = "UPDATE users SET `$key` = '" . $value . "'" .
                        " WHERE idusers = " . $this->users->idusers;
      } else {
          $query_pref = "REPLACE INTO `user_prefs` (`idusers`, `pref_name`, `pref_value`) " . 
                        " VALUES ( " . $this->users->idusers . 
                        ", " . " '" . mysql_real_escape_string($key) .  "', '" . $value . "')" ;
      }

      if(wrapper_mysql_db_query_writer($query_pref)) {
          return True;
      } else {
          $this->users->set_error_with_mysql_query($query_pref);
          return False;
      }
  }

  function setABD() {
      //Forbid Abandon for non owner
      if ($this->users->getOwnerId() != getPlayerId()) return;
      
    // Record classification only if this is not a "TYPE_RECORD" race and if no oldDuration is known
    $oldDuration=getOldDuration($this->races->idraces, $this->users->idusers);
    if ( $oldDuration == 0 ) {
      // replace into races_results (idraces , idusers , position , duration, longitude, latitude)
      //                       values ($1, $2, 0, $la_date, $longitude, $latitude);
      $result_timestamp = MAX_DURATION - time();
      $query_abandon = "REPLACE INTO races_results " .
        "        (idraces , idusers , position , duration, longitude, latitude)  " .
        " VALUES ( " . $this->users->engaged . ", " .
        $this->users->idusers . ", " .
        BOAT_STATUS_ABD . ", " .
        $result_timestamp . ", " .
        $this->lastPositions->long . ", " .
        $this->lastPositions->lat . ");"   ;

      wrapper_mysql_db_query_writer($query_abandon);
    }

    $oldengaged = $this->users->engaged;
    // Then subscribe to race 0
    $this->subscribeToRaces(0);

    logUserEvent($this->users->idusers , $oldengaged, "Abandon in race ~$oldengaged." );

  }

  function setDNF()
  {
    // Record classification
    // replace into races_results (idraces , idusers , position , duration, longitude, latitude)
    //                       values ($1, $2, 0, $la_date, $longitude, $latitude);
    $oldDuration=getOldDuration($this->races->idraces, $this->users->idusers);
    if ( $oldDuration == 0 ) {
      $result_timestamp = MAX_DURATION - time();
      $query_abandon = "REPLACE INTO races_results " .
        "        (idraces , idusers , position , duration, longitude, latitude)  " .
        " VALUES ( " . $this->users->engaged . ", " .
        $this->users->idusers . ", " .
        BOAT_STATUS_DNF . ", " .
        $result_timestamp . ", " .
        $this->lastPositions->long . ", " .
        $this->lastPositions->lat . ");"   ;

      wrapper_mysql_db_query_writer($query_abandon);
    }

    // Then subscribe to race 0
    $this->subscribeToRaces(0);
  }

  function setHTP()
  {
    // Record classification
    // replace into races_results (idraces , idusers , position , duration, longitude, latitude)
    //                       values ($1, $2, 0, $la_date, $longitude, $latitude);
    $oldDuration=getOldDuration($this->races->idraces, $this->users->idusers);
    if ( $oldDuration == 0 ) {
      $result_timestamp = MAX_DURATION - time();
      $query_abandon = "REPLACE INTO races_results " .
        "        (idraces , idusers , position , duration, longitude, latitude)  " .
        " VALUES ( " . $this->users->engaged . ", " .
        $this->users->idusers . ", " .
        BOAT_STATUS_HTP . ", " .
        $result_timestamp . ", " .
        $this->lastPositions->long . ", " .
        $this->lastPositions->lat . ");"   ;

      wrapper_mysql_db_query_writer($query_abandon);
      $this->users->logUserEvent(sprintf("Timelimit reached : HTP at %f , %f", $this->lastPositions->lat, $this->lastPositions->long ));
    }

    // Then subscribe to race 0
    $this->subscribeToRaces(0);
  }

  function setSTOPPED()
  {
    // Record classification
    // replace into races_results (idraces , idusers , position , duration, longitude, latitude)
    //                       values ($1, $2, 0, $la_date, $longitude, $latitude);
    $timestamp=time();
    $this->users->pilotmode      = PILOTMODE_WINDANGLE;
    $this->users->pilotparameter = 0;

    $query = "UPDATE users SET `pilotmode`=".PILOTMODE_WINDANGLE.
      ",`pilotparameter`=0," .
      "`lastchange`=" . $timestamp .
      " WHERE idusers=".$this->users->idusers;
    wrapper_mysql_db_query_writer($query); // or die("Query failed : " . mysql_error." ".$query);

    $this->updateAngles();
  }


  function subscribeToRaces($id)
  {
    $id = intval($id);
    $query11 = "UPDATE users SET engaged =" . $id . ", " .
      " pilotmode=2, " .
      " pilotparameter=0,  " .
      " nextwaypoint=1, " .
      " userdeptime=-1, " .
      " releasetime=0, " .
      " loch=0 " .
      " WHERE idusers = ".$this->users->idusers;
    $result11 = wrapper_mysql_db_query_writer($query11);

    if ( $id != 0 ) {
      $this->races = new races($id);
      $this->users->boattype = $this->races->boattype;

      // delete old positions from database for this race
      $this->deletePositions($id);
      // clear the races_loch table
      $this->deleteRaceLoch();

      // Purge all Pilototo tasks
      $this->users->pilototoPurge(0);

      // insert an initial position
      $query7 = "INSERT INTO positions SET`time` = ". time().
        ", `long` =". $this->races->startlong .
        ", `lat` =". $this->races->startlat.
        ", `idusers` = ".$this->users->idusers.
        ", `race` = ".$id;
      wrapper_mysql_db_query_writer($query7);

      // Delete old positions from races_results (in case of sub/unsub/sub again) (only if not TYPE_RECORD)
      if ( !$this->races->isRacetype(RACE_TYPE_RECORD) ) {
        $query_clean_races_results = "DELETE FROM races_results WHERE `idraces` = ". $id .
          " AND `idusers` = " . $this->users->idusers;
        wrapper_mysql_db_query_writer($query_clean_races_results);
      }

      // Delete all old entries from races_ranking
      $query_clean_races_ranking = "DELETE FROM races_ranking WHERE idusers= " .  $this->users->idusers ;
      wrapper_mysql_db_query_writer($query_clean_races_ranking);

      // Delete old invalid WP crossing from waypoint_crossing
      $query_clean_waypoint_crossing = "DELETE FROM waypoint_crossing WHERE validity=0 AND idusers=".
	$this->users->idusers." AND idraces=".$id;
      wrapper_mysql_db_query_writer($query_clean_waypoint_crossing);

      // Prepare the table races_ranking
      $query_clean_races_ranking = "INSERT INTO races_ranking ( idraces, idusers, loch, nwp, latitude, longitude) values " .
        " ( ". $id . ", " . $this->users->idusers . ", 0, 1, ".$this->races->startlat.", ".$this->races->startlong.")";

      wrapper_mysql_db_query_writer($query_clean_races_ranking);

      // Update boattype
      $query_boattype=" UPDATE users set boattype = '" . $this->races->boattype . "'" .
        "             ,    targetlat = 0, targetlong = 0, targetandhdg = -1        " .
        " WHERE idusers = " . $this->users->idusers;
      $result = wrapper_mysql_db_query_writer($query_boattype) or die("Query [$query_boattype] failed \n");

      logUserEvent($this->users->idusers , $id, "Engaged in race ~$id." );

    } else {
      $this->deleteCurrentRanking();
    }
    $this->users->engaged = $id;
  }


  function writeNewheading($mode, $boath = null, $param = null) {

      // We timestamp each change,
      // ==> to detect sleeping users who are in STOPPED mode due to coast crossing
      // Engine uses this field to set them DNF if the are sleeping for a long time
      // FIXME : does the lastchange field dups with the updated field ???
      $timestamp=time(); //Impact si différence au niveau temps des serveurs...
      $query = "UPDATE `users` ";
      $query_suffix = " `lastchange` = ". $timestamp . ", " .
                      " `ipaddr` = '". $_SESSION['IP'] . "'" .
                      " WHERE `idusers` = ".$this->users->idusers;

      switch ($mode) {
          case PILOTMODE_HEADING :
              //find angle and wind angle
              if (!is_null($boath)) $this->users->boatheading = $boath;
              $this->users->pilotmode = PILOTMODE_HEADING;
              $query .= "SET `pilotmode`=".PILOTMODE_HEADING.", " .
                        " `boatheading` = ". $this->users->boatheading . ", " .
                        " `pilotparameter` = ". $this->users->boatheading . ", " .
                        $query_suffix;
              $logmsg = "Update Angles : pim=" . $this->users->pilotmode . ", pip=" . $this->users->boatheading;
              break;
          case PILOTMODE_WINDANGLE :
              if (!is_null($param)) $this->users->pilotparameter = $param;
              $this->users->pilotmode = PILOTMODE_WINDANGLE;
              $query .= "SET `pilotmode`=". PILOTMODE_WINDANGLE.", " .
                      " `pilotparameter` = " . $this->users->pilotparameter . 
		      ", " . $query_suffix;
              $logmsg = "Update Angles : pim=" . $this->users->pilotmode . ", pip=" . $this->users->pilotparameter;
              break;
          case PILOTMODE_ORTHODROMIC :
          case PILOTMODE_BESTVMG :
          case PILOTMODE_VBVMG :
          case PILOTMODE_BESTSPEED :
              $this->users->pilotmode = $mode;
              $query .= "SET `pilotmode`=".$mode.", " .
                        $query_suffix;
              $logmsg = "Update Angles : pim=" . $this->users->pilotmode;
          break;
          default :
              //En principe, on ne doit jamais arriver là car les contrôles doivent être fait en amont de l'appel à la méthode
              $logmsg = "Update Angles : FAILED with pim = $mode";
              $this->users->logUserEvent($logmsg);
              $this->users->set_error($logmsg);
              return False;
      }
      if ($result = wrapper_mysql_db_query_writer($query)) {
          $this->users->logUserEvent($logmsg);
          $this->updateAngles();
          return True;
      } else {
          //Error d'accès sql ?
          $logmsg = "Update Angles : FAILED with pim = $mode, boatheading = $boath, pip = $param";
          $this->users->logUserEvent($logmsg);          
          $this->users->set_error($logmsg);
          $this->users->set_error_with_mysql_query($query);
          return False;
      }
  }

  /**
   * return the orthodromic heading from the current position
   * to the next mark
   * @return the heading in degrees.
   */
  function orthodromicHeading() {
    return ortho_heading($this->lastPositions->lat, $this->lastPositions->long,
       $this->LatNM, $this->LongNM);
  }

  /**
   * return the orthodromic heading from the current position
   * to the next mark
   * @return the heading in degrees.
   */
  function loxodromicHeading() {
    return loxo_heading($this->lastPositions->lat, $this->lastPositions->long,
       $this->LatNM, $this->LongNM);
  }

  /**
   * Write current loch in the loch table
   */
  function writeLoch($when = 0) {
    if ($when == 0) {
      $when = time();
    }
    $when -= ($when % 60); // clamp to lower minute
    $query7 = "INSERT INTO races_loch SET `time`=". $when .
      ", `loch`=".$this->users->loch.
      ", `idusers`=".$this->users->idusers.
      ", `idraces`=".$this->users->engaged;
    wrapper_mysql_db_query_writer($query7);
  }
  
  //this function says how many milles the user travelled during the last <duration>
  function distRecords($duration) {
    $timestamp = time();
    $timestamp -= ($timestamp % 60);
    
    $query = "SELECT `time`, `loch` ".
      " FROM races_loch WHERE idusers=".$this->users->idusers.
      " AND idraces=".$this->users->engaged.
      " AND time=" . ( $timestamp - $duration);
    
    $result = wrapper_mysql_db_query_reader($query);
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    if (!$row) {
      $query = "SELECT `time`, `loch` ".
	" FROM races_loch WHERE idusers=".$this->users->idusers.
	" AND idraces=".$this->users->engaged.
	" AND time > " . ( $timestamp - $duration - DELAYBETWEENUPDATE/2 ) .
	" ORDER BY time ASC LIMIT 1";
      
      $result = wrapper_mysql_db_query_reader($query);
      $row = mysql_fetch_array($result, MYSQL_ASSOC);
      if (!$row) {
	return array(0,0);
      }
    }
    
    $distance = $this->users->loch - $row['loch'];
    $time_elapsed = max($timestamp - $row['time'], 1);
    
    if ($time_elapsed == 1 && $distance > 10) {
      $distance = 0;
      $corrected_distance = 0;
    } else {
      $corrected_distance =  $distance *  $duration / $time_elapsed ;
    }
    //printf ("\ndistRecords for duration = %d s , elapsed = %d, dur/ela = %f,\n distance=%f, corrected=%f\n", $duration, $time_elapsed, $duration/$time_elapsed,$distance,$corrected_distance);
    return (array($distance,$corrected_distance));
  }

  function getCurrentRanking() {
      $ar = $this->getCurrentUserRanking();
      $ret = $ar['rankracing'];
      if ($ar['rankracing'] != $ar['rank']) {
          $ret .= " (".$ar['rank'].")";
      }
      $ret .= " / ".$ar['nbu'];
      return $ret;
  }

  function getCurrentUserRanking() {
      $query = "SELECT idusers FROM races_ranking WHERE idusers >0 AND dnm IS NOT NULL AND idraces = " . 
	$this->users->engaged . " ORDER BY nwp DESC, dnm ASC" ;
      $result = wrapper_mysql_db_query_reader($query) or die("Query failed : " . mysql_error." ".$query);
      $nbu=0; $rank = 0;
      while ($row = mysql_fetch_array($result, MYSQL_ASSOC) ) {
          if( $row['idusers'] == $this->users->idusers ) $rank=$nbu+1;
          $nbu++;
      }
      // we do add num_arrived boats to each counters
      $query = "SELECT count(*) AS nbarrived FROM races_results where position = " . BOAT_STATUS_ARR . " AND idraces = " . $this->users->engaged;
      $result = wrapper_mysql_db_query_reader($query) or die("Query failed : " . mysql_error." ".$query);
      $rowarrived = mysql_fetch_array($result, MYSQL_ASSOC);
      return array("rankracing" => $rank, "nbu" => $nbu+$rowarrived['nbarrived'],
                    "rank" => $rank+$rowarrived['nbarrived']);
  }
  
  function displayAbandonDiv() {
      //FIXME should be player logged !
      echo '<div id="abandon">';
      echo "<p>" . getLocalizedString("warning") . "</p>";
      $racesObj = $this->races;
      echo "<p>" . getLocalizedString("youengaged");
      echo " <b>" .   $racesObj->htmlRacenameLink() .  " (" . $racesObj->htmlIdracesLink()    .  ") " . "</b></p>";
  
      if ($this->users->engaged  != 0 && getLoggedPlayerObject()->isOwner(getLoginId())) {
          // The user may want to unsubscribe from this race
          echo htmlAbandonButton($this->users->idusers, $this->users->engaged);
      } else {
          //User is not owner of this boat !
          echo getLocalizedString("This is not your boat, only boat owner may abandon !");
      }
      echo '</div>';
  }
}



// Class excludedUser
// This class is used to implement tracks after a race, and to show posisions
// of dnf users

class excludedUsers
{
  var $users, //a user object
              //var deduced from others
    $lastPositions, //a postions object
    $hours,
    $anteLastPositions, //another positions obj
    $races ;    //include a race object


  // This one takes one more parameter than fullUsers (raceid)
  function excludedUsers($id, $raceid, $age = MAX_DURATION)
  {

    $this->users = getUserObject($id);

    //find last position and time interval from now
    $lastPositionsObject = new positions;
    $lastPositionsObject->getLastPositions($this->users->idusers, $raceid);

    $this->lastPositions = $lastPositionsObject;

    $anteLastPositionsObject = new positions;
    //    echo "Avant appel anteLast depuis users.class.php 2\n";
    $anteLastPositionsObject->getAnteLastPositions($this->users->idusers, $raceid);
    //   echo "Après appel anteLast depuis users.class.php 2\n";
    $this->anteLastPositions = $anteLastPositionsObject;

    $this->races = new races($raceid);
    $time = $this->lastPositions->time;
    $this->hours = (time() - $time )/3600 ;//everything is in GMT

  }
}
?>
