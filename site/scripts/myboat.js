<script type="text/javascript">
//<![CDATA[
var speed=<?php
  echo $usersObj->boatspeed ;
  ?>;
var boatheading = <?php
  //echo $usersObj->users->boatheading ;
  echo $boatdir ;
  ?>;
var wspeed = <?php
  echo $usersObj->wspeed;
  ?>;
var wheading =<?php
  echo $usersObj->wheading ;
  ?>;
var angle = <?php
           if ( $usersObj->users->pilotmode == PILOTMODE_WINDANGLE ) {
         $baww = round($usersObj->users->pilotparameter,1) ;
     } else {
         $twa = $usersObj->wheading - $usersObj->users->boatheading;
         if ($twa < -180 ) $twa +=360;
         if ($twa > 180 ) $twa -=360;
         if ( $twa > 0 ) {
             $amure = "tribord";
                   $baww = round($usersObj->boatanglewithwind,1) ;
         } else {
             $amure = "babord";
                   $baww =  -round($usersObj->boatanglewithwind,1) ;
         }
     }
     echo $baww;
  ?>;

/*
function expertmode()
{
     document.getElementById('expert').style.display = '' ;
     document.autopilot.expertcookie.value='yes';
     document.angle.expertcookie.value='yes';
     document.ortho.expertcookie.value='yes';
}

function beginnermode()
{
     document.getElementById('expert').style.display = 'none' ;
     document.autopilot.expertcookie.value='no';
     document.angle.expertcookie.value='no';
     document.ortho.expertcookie.value='no';
}
*/

function increment()
{
  boatheading=document.autopilot.boatheading.value;
  boatheading++;
  if ( boatheading >=360 ) boatheading -= 360;
  document.autopilot.boatheading.value = parseInt(boatheading);
}

function decrement()
{
  boatheading=document.autopilot.boatheading.value;
  boatheading--;
  if ( boatheading <0 ) boatheading += 360;
  document.autopilot.boatheading.value = parseInt(boatheading);
}

function incrementAngle()
{
  angle=document.angle.pilotparameter.value;
  angle++ ;
  if ( angle >180 ) angle -= 360;
  if ( angle <-180 ) angle += 360;
  document.angle.pilotparameter.value = parseInt(angle);
  document.angle.pim.value = parseInt(angle);
}

function decrementAngle()
{
  angle=document.angle.pilotparameter.value;
  angle--;
  if ( angle >180 ) angle -= 360;
  if ( angle <-180 ) angle += 360;
  document.angle.pilotparameter.value = parseInt(angle);
  document.angle.pim.value = parseInt(angle);
}

function tack()
{
  document.angle.pilotparameter.value = -(document.angle.pilotparameter.value) ;
  document.angle.pim.value = (document.angle.pilotparameter.value) ;
}


function updateBoatheading()
{
  boatheading = document.autopilot.boatheading.value;
}

//js conversion from a php function
/* 
    The PHP CODE :
    function angleDifference($a, $b)
    {
           while ( $a >= 360 ) $a-=360;
      $b += 180; while ( $b >= 360 ) $b-=360;

      return abs($a - $b);
    }
*/
function angleDifference(a, b)
{
  //Hell with JS!
  // a +180 is understood as a string 135180!
  _A=a ; while ( _A >= 360 ) _A-=360;
  _B=(b+180) ; while ( _B >= 360 ) _B-=360;

  //document.write('a = '+_A+' and  b ='+_B );
  RES = Math.abs( (_B-_A) );
  if ( RES > 180 ) RES=360-RES;

  //document.write('RES = '+RES );
  
  return RES;
}


function updateSpeed()
{
  speed = findboatspeed(angleDifference(boatheading, wheading)) ;
  //document.write('WH = '+wheading+ ' AD = '+ angleDifference(boatheading, wheading) +' BH = '+boatheading+ ' field = '+ document.autopilot.boatheading.value +' speed ='+speed);
  document.autopilot.speed.value = Math.round(speed*100)/100; //trunk it
}

//from a linear chart and an angle find speed
//linearcharts are arrays from the windchart function
function boatspeedfromlinearchart(chart, angle)
{
  //find angleinf and anglesup from tha charts
  cur = 0;
  prev = cur;

  for (var cur in chart.hashtable)
    {
      if (angle<=cur)
  break;
      prev = cur;
    }

  //in every of them, cur refers to angle and hashtable[cur] to speed
  //find medium boatspeed value
  return(
   (chart.get(prev) + (angle - prev)
    * (chart.get(cur) - chart.get(prev))
    / (cur - prev)));
}


function boatspeedfromcharts(windInf, windSup, windInfBoatSpeed,
             windSupBoatSpeed, windspeed, boatangle)
{

      if (windInf != windSup)
  return(
         windInfBoatSpeed + (windspeed - windInf)
         * (windSupBoatSpeed - windInfBoatSpeed)
         / (windSup - windInf));
      else
  return windInfBoatSpeed;

 }

//this function is a conversion from the same in php
//to be executed on client side
//generate a js function that is only dependant from angledifference
function findboatspeed(angledifference)
{
      var windInfChart = new Hashtable();
      var windSupChart = new Hashtable();

      angledifference = Math.abs(Math.round(angledifference));

      if (angledifference == 180)
      angledifference = 179;

      //too much complicated if 180
      <?php
    $windinf = floor( $usersObj->wspeed);
    $windsup = ceil( $usersObj->wspeed);
    if ($windinf >= 60) {
	$windinf = 60;
    }
    if ( $windsup >= 60) {
	$windsup = 60;
    }
      ?>

    windInf =  <?php echo $windinf;?>;
    windSup =  <?php echo $windsup;?>;


      //converting php hash table to javascript hashstable (UGLY)
    <?php
    $fwindinf = (float) $windinf;
    $fwindsup = (float) $windsup;

    $temp_vlmc_context = new vlmc_context();
    shm_lock_sem_construct_polar_context($temp_vlmc_context, 1);  

    for ($t_angle = 0; $t_angle <= 180; $t_angle += 5) {
	$t_boatspeed = VLM_find_boat_speed_context($temp_vlmc_context, 
			$usersObj->users->boattype, $fwindinf, (float)$t_angle);
	echo "windInfChart.put(\"$t_angle\", $t_boatspeed);\n";
    }
    
    for ($t_angle = 0; $t_angle <= 180; $t_angle += 5) {
	$t_boatspeed = VLM_find_boat_speed_context($temp_vlmc_context,
		        $usersObj->users->boattype, $fwindsup, (float)$t_angle);
	echo "windSupChart.put(\"$t_angle\", $t_boatspeed);\n";
    }
    shm_unlock_sem_destroy_polar_context($temp_vlmc_context, 1);
    ?>

  windInfBoatSpeed = boatspeedfromlinearchart(windInfChart, angledifference);
  windSupBoatSpeed = boatspeedfromlinearchart(windSupChart, angledifference);

 //   document.write("windinfboatspeed = "+windInfBoatSpeed+" windSupBoatSpeed = "+windSupBoatSpeed);


  return boatspeedfromcharts(
             windInf, windSup,
             windInfBoatSpeed, windSupBoatSpeed, wspeed,
             angledifference);

}

function updateUserPref()
//FIXME: useless, isn't it ?
{
  val = document.mercator.maparea.value;
  alert(val)
}

var mapprefcounter = 0; 

//Something changed, then save it.
function mapprefChanged()
{
  mapprefcounter++;
}

//Reset mapprefs saving to on (when submitting, typically)
function mapprefSubmitted()
{
    if (mapprefcounter > 0) {
        document.mapprefs.save.value='on';
    } else {
        document.mapprefs.save.value='off';
    }      
    mapprefcounter = 0;
}

//]]>
</script>
