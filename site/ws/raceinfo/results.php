<?
    include_once("config.php");
    include_once("wslib.php");

    header("content-type: text/plain; charset=UTF-8");

    //FIXME : types are badly checked
    $ws = new WSBaseRace();
    $now = time();
    
    $ws->require_idr();
    $status = intval($ws->check_cgi('status', "", BOAT_STATUS_ARR));
    $limit = intval($ws->check_cgi_intzero('limit', "LIMIT01", "LIMIT02", 0));
    $wp = intval($ws->check_cgi_intzero('wp', "WP01", "WP02", 0));
    
    $races = new races($ws->idr);

    //FIXME : should refactor in lib/phpcommon

    if ( $wp == 0) {
        $query = "SELECT RR.position as status, RR.duration + RR.penalty duration, RR.idusers idusers, username as boatpseudo, 
                            color, country, boatname, longitude, latitude, RR.deptime deptime, RR.loch loch, penalty
                  FROM      races_results RR, users US
                  WHERE     idraces=".$races->idraces."
                  AND       US.idusers = RR.idusers
                  AND       position=" . $status . " " ;
    } else {
        $query = "SELECT " . BOAT_STATUS_ARR . " status, WC.time - WC.userdeptime duration, WC.idusers idusers, username as boatpseudo,
                            color, country, boatname, null longitude, null latitude, WC.userdeptime deptime, null loch, null penalty
                  FROM      waypoint_crossing WC, users US
                  WHERE     WC.idraces=".$races->idraces." AND WC.validity=1
                  AND       WC.time > WC.userdeptime AND WC.userdeptime > 0 
                  AND       US.idusers = WC.idusers   
                  AND       idwaypoint=" . $wp . " " ;
    }

    if ( $races->isRacetype(RACE_TYPE_RECORD) ) {
        // Pour une course record : c'est le temps de course par défaut
        $query .= " ORDER BY duration ASC";
    } else {
        // Pour une course classique, c'est tout simplement la date d'arrivée (on l'a avec deptime + duration)
        $query .= " ORDER BY duration + penalty + deptime ASC";
    }
    if ($limit > 0) $query .= " LIMIT ".$limit;

    $res = $ws->queryRead($query);

    $ws->answer['request'] = Array('idr' => $ws->idr, 'time' => $now, 'status' => $status);
    if ($wp != 0) $ws->answer['request']['wp'] = $wp;
    if ($limit > 0) $ws->answer['request']['limit'] = $limit;
    $ws->answer['results'] = Array();
    
    $position = 0;
    
    while ($row = mysql_fetch_assoc($res)) {
        // N'entrent dans les tableaux que les bateaux effectivement en course
        $row['latitude'] /= 1000.;
        $row['longitude'] /= 1000.;
        $position += 1;
        $row['rank'] = $position;
        $ws->answer['results'][$row['idusers']] = $row;
    }

    if ($wp == 0) { //on affiche seulement ces infos pour une course terminée
        list ($num_arrived , $num_racing, $num_engaged) = getNumOpponents($races->idraces);    
        $ws->answer['nb_arrived'] = $num_arrived;
        $ws->answer['nb_racing'] = $num_racing;
        $ws->answer['nb_engaged'] = $num_engaged;
    }

    $ws->reply_with_success();

?>
