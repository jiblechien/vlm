//
// VLMBoat layer handling displaying vlm boats, traj
//

/*const BOAT_ICON = 0
const BOAT_WP_MARKER = 1
const BOAT_TRACK = 2
const BOAT_FORECAST_TRACK = 3
const BOAT_POLAR = 4
*/
const VLM_COORDS_FACTOR = 1000

var MapOptions = {
  // Projection mercator sphérique (type google map ou osm)
  projection: new OpenLayers.Projection("EPSG:900913"),
  // projection pour l'affichage des coordonnées
  displayProjection: new OpenLayers.Projection("EPSG:4326"),
  // unité : le m
  units: "m",
  maxResolution: 156543.0339,
  maxExtent: new OpenLayers.Bounds(-20037508.34, -20037508.34,
    20037508.34, 20037508.34),
  restrictedExtent: new OpenLayers.Bounds(-40037508.34, -20037508.34,
    40037508.34, 20037508.34),
  eventListeners:
    {
      "zoomend":HandleMapZoomEnd,
      "featureover": HandleFeatureOver,
      "featureout": HandleFeatureOut,
      "featureclick":HandleFeatureClick
    }
};

// Control to handle drag of User WP
var DrawControl = null;
var BoatFeatures = [];

function SetCurrentBoat(Boat, CenterMapOnBoat,ForceRefresh) 
{
  CheckBoatRefreshRequired(Boat, CenterMapOnBoat,ForceRefresh);
}

function CheckBoatRefreshRequired(Boat, CenterMapOnBoat, ForceRefresh) 
{
  // Check Params.
  if (typeof Boat === "undefined" || !Boat)
  {
    return;
  }
  var CurDate = new Date();
  var NextUpdate = new Date(0);
  var NeedPrefsRefresh = (typeof Boat!=="undefined" && (typeof Boat.VLMInfo==="undefined" || typeof Boat.VLMInfo.AVG === "undefined"));

  // Update preference screen according to current selected boat
  UpdatePrefsDialog(Boat);

  if (typeof Boat != 'undefined' &&
    typeof Boat.VLMInfo != 'undefined' && typeof Boat.VLMInfo.LUP != 'undefined') {
    NextUpdate.setUTCSeconds(parseInt(Boat.VLMInfo.LUP)+60);
  }

  if (((typeof Boat !== 'undefined') && (CurDate >= NextUpdate)) ||
      ( (typeof Boat !== "undefined") && (ForceRefresh)) ) 
  {
    console.log("Loading boat info from server....")
    // request current boat info
    ShowPb("#PbGetBoatProgress");
    $.get("/ws/boatinfo.php?forcefmt=json&select_idu=" + Boat.IdBoat,
      function (result) {
        // Check that boat Id Matches expectations
        if (Boat.IdBoat == result.IDU) {
          // Set Current Boat for player
          _CurPlayer.CurBoat = Boat;

          // Store BoatInfo, update map
          Boat.VLMInfo = result;

          // Fix Lon, and Lat scale
          Boat.VLMInfo.LON /= VLM_COORDS_FACTOR;
          Boat.VLMInfo.LAT /= VLM_COORDS_FACTOR;

          // force refresh of settings if was not initialized
          if (NeedPrefsRefresh) 
          {
            UpdatePrefsDialog(Boat);
          }

          // update map if racing
          if (Boat.VLMInfo.RAC != "0") 
          {

            

            if (typeof Boat.RaceInfo ==="undefined" || typeof Boat.RaceInfo.idraces === 'undefined') 
            {
              // Get race info if first request for the boat
              $.get("/ws/raceinfo.php?idrace=" + Boat.VLMInfo.RAC,
                function (result) {
                  // Save raceinfo with boat
                  Boat.RaceInfo = result;

                  DrawRaceGates(Boat.RaceInfo, Boat.VLMInfo.NWP,true);

                  // Update the racename display if needed
                  var RaceName = $("#RaceName").first();

                  if (typeof RaceName != "undefined") {
                    if (_CurPlayer.CurBoat == Boat) {
                      $("#RaceName").text(Boat.RaceInfo.racename);
                    }
                  }
                }

              );
              $.get("/ws/raceinfo/exclusions.php?idrace=" + Boat.VLMInfo.RAC,
                function (result) {
                  if (result.success) {
                    var Polygons = []
                    var CurEndPoint
                    var CurPolyPointsList = []
                    var index

                    for (index in result.Exclusions) {
                      var Seg = result.Exclusions[index]

                      if (typeof CurEndPoint === 'undefined' || (CurEndPoint[0] !== Seg[0][0] && CurEndPoint[1] !== Seg[0][1])) {
                        if (typeof CurEndPoint != 'undefined') {
                          // Changing Polygons
                          Polygons.push(CurPolyPointsList);
                          CurPolyPointsList = []
                        }
                        // Add segment Start to current point list
                        CurPolyPointsList.push(Seg[0])
                      }

                      CurEndPoint = Seg[1];
                      // Add segment end  to current point list
                      CurPolyPointsList.push(Seg[1])
                    }

                    Polygons.push(CurPolyPointsList)
                    Boat.Exclusions=Polygons;
                    DrawRaceExclusionZones(VLMBoatsLayer,Polygons)
                  }

                }
              )

            }
            else
            {
              //Redraw gates and exclusions from cache
              DrawRaceGates(Boat.RaceInfo, Boat.VLMInfo.NWP,false);
              DrawRaceExclusionZones(VLMBoatsLayer,Boat.Exclusions);
            }


            // Get boat track for the last 24h
            var end = Math.floor(new Date() / 1000)
            var start = end - 24 * 3600
            $.get("/ws/boatinfo/tracks_private.php?idu=" + Boat.IdBoat + "&idr=" + Boat.VLMInfo.RAC + "&starttime=" + start + "&endtime=" + end,
              function (result) {
                if (result.success) {
                  if (typeof Boat.Track !== "undefined")
                  {
                    Boat.Track.length = 0;
                  }
                  else
                  {
                    Boat.Track=[];
                  }
                  for (index in result.tracks) {
                    var P = new VLMPosition(result.tracks[index][1] / 1000., result.tracks[index][2] / 1000.)
                    Boat.Track.push(P);
                  }
                  DrawBoat(Boat);
                }
              }
            )

            // Get Rankings
            LoadRankings(Boat);

            // Draw Boat, course, tracks....
            DrawBoat(Boat, CenterMapOnBoat);

            // Update Boat info in main menu bar
            UpdateInMenuRacingBoatInfo(Boat);

          }
          else 
          {
            // Boat is not racing
            //GetLastRacehistory();
            UpdateInMenuDockingBoatInfo(Boat);
          }
        }

        HidePb("#PbGetBoatProgress");
      }
    )


  }
  else if (Boat)
  {
    // Draw from last request
    DrawBoat(Boat, CenterMapOnBoat);
    DrawRaceGates(Boat.RaceInfo, Boat.VLMInfo.NWP,false);
    DrawRaceExclusionZones(VLMBoatsLayer,Boat.Exclusions);
  }
}



function DrawBoat(Boat, CenterMapOnBoat) 
{

  if (typeof Boat==="undefined" || !Boat)
  {
    // Ignore call, if no boat is provided...
    return;
  }
  var Pos = new OpenLayers.Geometry.Point(Boat.VLMInfo.LON, Boat.VLMInfo.LAT);
  var PosTransformed = Pos.transform(MapOptions.displayProjection, MapOptions.projection)
  //WP Marker
  var WP = Boat.GetNextWPPosition();
  var WPTransformed = new OpenLayers.Geometry.Point(WP.Lon.Value, WP.Lat.Value).transform(MapOptions.displayProjection, MapOptions.projection);
  var UpdatedFeatures = [];

  var ForecastPos = new VLMPosition(Boat.VLMInfo.LON, Boat.VLMInfo.LAT).ReachDistLoxo(12 * Boat.VLMInfo.BSP * Boat.VLMInfo.VAC / 3600, Boat.VLMInfo.HDG);
  var ForecastPosTransformed = new OpenLayers.Geometry.Point(ForecastPos.Lon.Value, ForecastPos.Lat.Value).transform(MapOptions.displayProjection, MapOptions.projection);

  // Remove features, before recreate and re-add
  // Can't figure how to move/update the features properly
  for (index in BoatFeatures)
  {
    // Beurk, but does the job anyways
    VLMBoatsLayer.removeFeatures(BoatFeatures[index]);
    VLMDragLayer.removeFeatures(BoatFeatures[index]);
  }

  BoatFeatures = [];

  if (DrawControl === null ) 
  {
  /*  console.log("DrawControl Deactivate "+DrawControl.id)
    DrawControl.deactivate();
    map.removeControl(DrawControl);
    console.log("Remove drawcontrol" + DrawControl.id);
    DrawControl = null;
  }*/
    DrawControl = new OpenLayers.Control.DragFeature(VLMDragLayer, 
      {
        onDrag: function(feature,pixel)
                {
                  console.log("Dragging "+feature.id);;
                },
        onComplete: function (feature, pixel) 
        {
          var dest = map.getLonLatFromPixel(pixel);
          var WGSDest = dest.transform(new OpenLayers.Projection("EPSG:900913"), new OpenLayers.Projection("EPSG:4326"));
          var PDest = new VLMPosition(WGSDest.lon, WGSDest.lat);

          console.log("DragComplete "+feature.id);
          VLMBoatsLayer.removeFeatures(feature);
          // Use CurPlayer, since the drag layer is not associated to the proper boat
          SendVLMBoatWPPos(_CurPlayer.CurBoat, PDest)
          DrawControl.deactivate();
          DrawControl.activate();
        }
      }
    );
    map.addControl(DrawControl)
    DrawControl.activate();
    //console.log("Added & activated drawcontrol" + DrawControl.id);
  }
  
    //Boat.DrawControl.modify.mode = OpenLayers.Control.ModifyFeature.DRAG;
  

  // Boat Marker
  var BoatIcon = new OpenLayers.Feature.Vector(
    PosTransformed,
    { "Id": Boat.IdBoat },
    { externalGraphic: 'images/target.svg', graphicHeight: 64, graphicWidth: 64, rotation: Boat.VLMInfo.HDG }
  );
  VLMBoatsLayer.addFeatures(BoatIcon);
  BoatFeatures.push(BoatIcon)
  
  // Waypoint marker    
  var WPMarker=new OpenLayers.Feature.Vector(
    WPTransformed,
    {},
    { externalGraphic: 'images/WP_Marker.gif', graphicHeight: 64, graphicWidth: 64 }
  );
  BoatFeatures.push(WPMarker);
  VLMDragLayer.addFeatures(WPMarker);
  //console.log("Added Pos Feature "+ WPMarker.id);
  // Last 24h track  
  if (typeof Boat.Track !== "undefined" && Boat.Track.length > 0)
   {
    var PointList = [];

    for (index in Boat.Track) {
      var P = Boat.Track[index];
      var P1 = new OpenLayers.Geometry.Point(P.Lon.Value, P.Lat.Value);
      var P1_PosTransformed = P1.transform(MapOptions.displayProjection, MapOptions.projection)

      PointList.push(P1_PosTransformed)

    }

    var BoatTrack = new OpenLayers.Feature.Vector(
      new OpenLayers.Geometry.LineString(PointList),
      {
        "type": "HistoryTrack",
        "TrackColor": "#" + Boat.VLMInfo.COL
      });
    VLMBoatsLayer.addFeatures(BoatTrack);
    BoatFeatures.push(BoatTrack);
  }
  
  
  // Forecast Track
  var TrackPointList = [];
  TrackPointList.push(P1_PosTransformed);
  TrackPointList.push(ForecastPosTransformed);

  var TrackForecast= new OpenLayers.Feature.Vector(
    new OpenLayers.Geometry.LineString(TrackPointList),
    {
      "type": "ForecastPos"
    });

  BoatFeatures.push(TrackForecast);
  VLMBoatsLayer.addFeatures(TrackForecast);

  // Draw polar
  var PolarPointList = PolarsManager.GetPolarLine(Boat.VLMInfo.POL, Boat.VLMInfo.TWS, DrawBoat, Boat);
  var Polar = [];

  // MakePolar in a 200x200 square
  //var BoatPosPixel = map.getPixelFromLonLat(new OpenLayers.LonLat(Boat.VLMInfo.LON, Boat.VLMInfo.LAT));
  var BoatPosPixel = map.getViewPortPxFromLonLat(PosTransformed);
  var scale = 50 * map.resolution;
  for (index in PolarPointList) 
  {
    var Alpha = 5 * Math.floor(index);
    var Speed = parseFloat(PolarPointList[index]);

    var PixPos = new OpenLayers.Geometry.Point(
      PosTransformed.x + Math.sin(Deg2Rad(Alpha + Boat.VLMInfo.TWD)) * scale * Speed,
      PosTransformed.y + Math.cos(Deg2Rad(Alpha + Boat.VLMInfo.TWD)) * scale * Speed);

    //var P = map.getLonLatFromPixel(PixPos);
    //var PPoint = new OpenLayers.Geometry.Point(PixPos);
    Polar.push(PixPos);
  }
  
  var BoatPolar = new OpenLayers.Feature.Vector(
    new OpenLayers.Geometry.LineString(Polar),
    {
      "type": "Polar",
      "WindDir": Boat.VLMInfo.TWD
    });

  BoatFeatures.push(BoatPolar)
  VLMBoatsLayer.addFeatures(BoatPolar);
  
  // opponents  and opponents tracks
  DrawOpponents(Boat,VLMBoatsLayer,BoatFeatures);

  if (typeof Boat.OppTrack !== "undefined" && Boat.OppTrack.length > 0)
  {
    var TrackPoints=[];
    for (TrackIndex in Boat.OppTrack)
    {
      var T = Boat.OppTrack[TrackIndex];

      if ( (T.DatePos.length>1) && ((!T.LastShow) || (T.LastShow < new Date()/1000+60)) )
      {
        for (PointIndex in T.DatePos)
        {
          var P = T.DatePos[PointIndex];
          var Pi = new OpenLayers.Geometry.Point(P.lon, P.lat);
          var Pi_PosTransformed = Pi.transform(MapOptions.displayProjection, MapOptions.projection)

          TrackPoints.push(Pi_PosTransformed)
        }
        var OppTrack = new OpenLayers.Feature.Vector(
        new OpenLayers.Geometry.LineString(TrackPoints),
        {
          "type": "HistoryTrack",
          "TrackColor": "#" + T.TrackColor
        });
        T.LastShow = new Date();
        VLMBoatsLayer.addFeatures(OppTrack);
        BoatFeatures.push(OppTrack);
      }
    } 
  }
  if (CenterMapOnBoat)
  {
    // Set Map Center to current boat position
    var l = new OpenLayers.LonLat(Boat.VLMInfo.LON, Boat.VLMInfo.LAT).transform(MapOptions.displayProjection, MapOptions.projection);

    // Fix Me : find a way to use a proper zoom factor (dist to next WP??)
    if (isNaN(l.lat) || isNaN(l.lon))
    {
      var i = 0;
    }
    
    map.setCenter(l);
    
  }
}

// allow testing of specific renderers via "?renderer=Canvas", etc
var renderer = OpenLayers.Util.getParameters(window.location.href).renderer;
renderer = (renderer) ? [renderer] : OpenLayers.Layer.Vector.prototype.renderers;

var VectorStyles = new OpenLayers.Style(
  {
    strokeColor: "#00FF00",
    strokeOpacity: 1,
    strokeWidth: 3,
    fillColor: "#FF5500",
    fillOpacity: 0.5,

  },
  {
    rules:
    [
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: 'buoy'
          }),
          symbolizer: {
            // if a feature matches the above filter, use this symbolizer
            label: "${name}\n${Coords}",
            //pointRadius: 6,
            pointerEvents: "visiblePainted",
            // label with \n linebreaks

            //fontColor: "${favColor}",
            fontSize: "1.5em",
            //fontFamily: "Courier New, monospace",
            //fontWeight: "bold",
            labelAlign: "left", //${align}",
            labelXOffset: "4",//${xOffset}",
            labelYOffset: "-12",//${yOffset}",
            //labelOutlineColor: "white",
            //labelOutlineWidth: 2
            externalGraphic: "images/${GateSide}",
            graphicWidth: 36,
            fillOpacity: 1

          }
        }
        ),
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: "crossonce"
          }),
          symbolizer: {
            xOffset: 1,
            yOffset: 1,
            strokeColor: "black",
            strokeOpacity: 0.5,
            strokeWidth: 4,
            strokeDashstyle: "dashdot"
          }
        }
        ),

      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: "marker"
          }),
          symbolizer: {
            externalGraphic: "images/BuoyDirs/${BuoyName}",
            rotation: "${CrossingDir}",
            graphicWidth: 48
          }
        }
        ),
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: "NextGate"
          }),
          symbolizer: {
            strokeColor: "#FF0000",
            strokeOpacity: 1,
            strokeWidth: 3
          }
        }
        ),
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: "ValidatedGate"
          }),
          symbolizer: {
            strokeColor: "#0000FF",
            strokeOpacity: 0.5,
            strokeWidth: 3
          }
        }
        ),
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: "FutureGate"
          }),
          symbolizer: {
            strokeColor: "#FF0000",
            strokeOpacity: 0.5,
            strokeWidth: 3
          }
        }
        ),
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: "ForecastPos"
          }),
          symbolizer: {
            strokeColor: "black",
            strokeOpacity: 0.75,
            strokeWidth: 1,
            strokeDashstyle: "dot"

          }
        }
        ),
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: "HistoryTrack"
          }),
          symbolizer: {
            strokeOpacity: 0.5,
            strokeWidth: 2,
            strokeColor: "${TrackColor}"
          }
        }
        ),
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: "Polar"
          }),
          symbolizer: {
            strokeColor: "white",
            strokeOpacity: 0.75,
            strokeWidth: 2
          }
        }
        ),
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: "ExclusionZone"
          }),
          symbolizer: {
            strokeColor: "red",
            strokeOpacity: 0.95,
            strokeWidth: 2,
            fillColor: "#FF5500",
            fillOpacity: 0.5
          }
        }
        ),
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          filter: new OpenLayers.Filter.Comparison({
            type: OpenLayers.Filter.Comparison.EQUAL_TO,
            property: "type", // the "foo" feature attribute
            value: 'opponent'
          }),
          symbolizer: {
            // if a feature matches the above filter, use this symbolizer
            label: "${idboat} - ${name}",
            //pointRadius: 6,
            pointerEvents: "visiblePainted",
            // label with \n linebreaks

            //fontColor: "${favColor}",
            fontSize: "1.5em",
            //fontFamily: "Courier New, monospace",
            //fontWeight: "bold",
            labelAlign: "left", //${align}",
            labelXOffset: "4",//${xOffset}",
            labelYOffset: "-12",//${yOffset}",
            //labelOutlineColor: "white",
            //labelOutlineWidth: 2
            externalGraphic: "images/opponent${IsTeam}.png",
            graphicWidth: 12,
            fillOpacity: 1

          }
        }
        ),
      new OpenLayers.Rule
        (
        {
          // a rule contains an optional filter
          elsefilter: true,
          symbolizer: {
          }
        }

        )


    ]
  }
);

var LayerListeners = {
  featureclick: function (e) {
    console.log(e.object.name + " says: " + e.feature.id + " clicked.");
    return false;
  },
  nofeatureclick: function (e) {
    console.log(e.object.name + " says: No feature clicked.");
    return false;
  }
};

var VLMBoatsLayer = new OpenLayers.Layer.Vector("VLM Boats and tracks", {
  styleMap: new OpenLayers.StyleMap(VectorStyles),
  renderers: renderer
});

var VLMDragLayer = new OpenLayers.Layer.Vector("VLM Waypoints", {
  styleMap: new OpenLayers.StyleMap(VectorStyles),
  renderers: renderer,
  eventListeners: LayerListeners
});

// Background load controller from ext html file
function GetBoatControllerPopup() {
  $("#BoatController").load("BoatController.html")
  return '<div id="BoatController"></div>';
}

const WP_TWO_BUOYS = 0
const WP_ONE_BUOY = 1
const WP_GATE_BUOY_MASK = 0x000F
/* leave space for 0-15 types of gates using buoys
   next is bitmasks */
const WP_DEFAULT = 0
const WP_ICE_GATE_N = (1 << 4)
const WP_ICE_GATE_S = (1 << 5)
const WP_ICE_GATE_E = (1 << 6)
const WP_ICE_GATE_W = (1 << 7)
const WP_GATE_KIND_MASK = 0xFFF0
/* allow crossing in one direction only */
const WP_CROSS_CLOCKWISE = (1 << 8)
const WP_CROSS_ANTI_CLOCKWISE = (1 << 9)
/* for future releases */
const WP_CROSS_ONCE = (1 << 10)

var RaceGates = [];
var Exclusions = [];

function DrawRaceGates(RaceInfo, NextGate, IsVLMCoords) {

  for (index in RaceGates)
  {
    VLMBoatsLayer.removeFeatures(RaceGates[index]);
  }
  // Loop all gates
  for (index in RaceInfo.races_waypoints) {
    // Draw a single race gates
    var WP = RaceInfo.races_waypoints[index];

    // Fix coords scales
    if (IsVLMCoords)
    {
      WP.longitude1 /= VLM_COORDS_FACTOR;
      WP.latitude1 /= VLM_COORDS_FACTOR;
      WP.longitude2 /= VLM_COORDS_FACTOR;
      WP.latitude2 /= VLM_COORDS_FACTOR;
    }
    var cwgate = !(WP.wpformat & WP_CROSS_ANTI_CLOCKWISE);

    // Draw WP1
    AddBuoyMarker(VLMBoatsLayer,RaceGates, "WP" + index + " " + WP.libelle + '\n', WP.longitude1, WP.latitude1, cwgate);


    // Second buoy (if any)
    if ((WP.wpformat & WP_GATE_BUOY_MASK) == WP_TWO_BUOYS) {
      // Add 2nd buoy marker
      AddBuoyMarker(VLMBoatsLayer,RaceGates, "", WP.longitude2, WP.latitude2, !cwgate);
    }
    else {
      // No Second buoy, compute segment end
      var P = new VLMPosition(WP.longitude1, WP.latitude1);
      var Dest = P.ReachDistLoxo(2500, 180 + parseFloat(WP.laisser_au));
      WP.longitude2 = Dest.Lon.Value;
      WP.latitude2 = Dest.Lat.Value;
    }

    // Draw Gate Segment
    AddGateSegment(VLMBoatsLayer,RaceGates, WP.longitude1, WP.latitude1, WP.longitude2, WP.latitude2, (NextGate == index), (index < NextGate), (WP.wpformat & WP_GATE_KIND_MASK));

  }
}

function DrawRaceExclusionZones(Layer,Zones)
{

  var index

  for (index in Exclusions)
  {
    Layer.removeFeatures(Exclusions[index]);
  }

  for (index in Zones)
  {
    DrawRaceExclusionZone(Layer,Exclusions,Zones[index])
  }

}

function DrawRaceExclusionZone(Layer,ExclusionZones, Zone) 
{

  var index
  var PointList=[];

  for (index in Zone) 
  {

    var P = new OpenLayers.Geometry.Point(Zone[index][1], Zone[index][0]);
    var P_PosTransformed = P.transform(MapOptions.displayProjection, MapOptions.projection)

    PointList.push(P_PosTransformed);

  }
  var Attr = null;

  Attr = { type: "ExclusionZone" };
  var ExclusionZone = new OpenLayers.Feature.Vector(
    new OpenLayers.Geometry.Polygon( new OpenLayers.Geometry.LinearRing(PointList)),
    Attr
    , null);

  Layer.addFeatures(ExclusionZone);
  ExclusionZones.push(ExclusionZone);

}

function AddGateSegment(Layer,Gates, lon1, lat1, lon2, lat2, IsNextWP, IsValidated, GateType) {
  var P1 = new OpenLayers.Geometry.Point(lon1, lat1);
  var P2 = new OpenLayers.Geometry.Point(lon2, lat2);
  var P1_PosTransformed = P1.transform(MapOptions.displayProjection, MapOptions.projection)
  var P2_PosTransformed = P2.transform(MapOptions.displayProjection, MapOptions.projection)
  var PointList = [];

  PointList.push(P1_PosTransformed);
  PointList.push(P2_PosTransformed);

  var Attr = null;

  if (IsNextWP) {
    Attr = { type: "NextGate" };
  }
  else if (IsValidated) {
    Attr = { type: "ValidatedGate" };
  }
  else {
    Attr = { type: "FutureGate" };
  }
  var WP = new OpenLayers.Feature.Vector(
    new OpenLayers.Geometry.LineString(PointList),
    Attr
    , null);

  Layer.addFeatures(WP);
  Gates.push(WP);
  if (GateType != WP_DEFAULT) {
    // Debug testing of the geo calculation functions
    /*{
      // Rumb line LAX-JFK = 2164.6 nm
      var P1 = new Position(  -(118+(24/60)),33+ (57/60));
      var P2 = new Position (-(73+(47/60)),40+(38/60));
      console.log("loxo dist : " + P1.GetLoxoDist(P2));
      console.log("loxo angle: " + P1.GetLoxoCourse(P2));

    }*/
    var P1 = new VLMPosition(lon1, lat1);
    var P2 = new VLMPosition(lon2, lat2);
    var MarkerDir = P1.GetLoxoCourse(P2);
    var MarkerPos = P1.ReachDistLoxo(P2, 0.5);
    // Gate has special features, add markers
    if (GateType & WP_CROSS_ANTI_CLOCKWISE) {
      MarkerDir -= 90;
      AddGateDirMarker(VLMBoatsLayer,Gates, MarkerPos.Lon.Value, MarkerPos.Lat.Value, MarkerDir);
    }
    else if (GateType & WP_CROSS_CLOCKWISE) {
      MarkerDir += 90;
      AddGateDirMarker(VLMBoatsLayer,Gates, MarkerPos.Lon.Value, MarkerPos.Lat.Value, MarkerDir);
    }

    if (GateType & WP_CROSS_ONCE) {
      // Draw the segment again as dashed line for cross once gates
      var WP = new OpenLayers.Feature.Vector(
        new OpenLayers.Geometry.LineString(PointList),
        { type: "crossonce" }
        , null);

      Layer.addFeatures(WP);
      Gates.push(WP);
    }

  }


}

const MAX_BUOY_INDEX = 16;
var BuoyIndex = Math.floor(Math.random() * MAX_BUOY_INDEX);
function AddGateDirMarker(Layer,Gates, Lon, Lat, Dir) {
  var MarkerCoords = new VLMPosition(Lon, Lat);
  var MarkerPos = new OpenLayers.Geometry.Point(MarkerCoords.Lon.Value, MarkerCoords.Lat.Value);
  var MarkerPosTransformed = MarkerPos.transform(MapOptions.displayProjection, MapOptions.projection)
  var Marker = new OpenLayers.Feature.Vector(MarkerPosTransformed,
    {
      "type": 'marker',
      "BuoyName": "BuoyDir" + BuoyIndex + ".png",
      "CrossingDir": Dir
    }
  );
  // Rotate buoys...
  BuoyIndex++;
  BuoyIndex %= (MAX_BUOY_INDEX + 1);

  Layer.addFeatures(Marker);
  Gates.push(Marker);
}


function AddBuoyMarker(Layer,Gates, Name, Lon, Lat, CW_Crossing) {
  var WP_Coords = new VLMPosition(Lon, Lat);
  var WP_Pos = new OpenLayers.Geometry.Point(WP_Coords.Lon.Value, WP_Coords.Lat.Value);
  var WP_PosTransformed = WP_Pos.transform(MapOptions.displayProjection, MapOptions.projection)
  var WP;

  if (CW_Crossing) {
    WP = new OpenLayers.Feature.Vector(WP_PosTransformed,
      {
        "name": Name,
        "Coords": WP_Coords.ToString(),
        "type": 'buoy',
        "GateSide": "Buoy1.png"
      }
    );
  }
  else {
    WP = new OpenLayers.Feature.Vector(WP_PosTransformed,
      {
        "name": Name,
        "Coords": WP_Coords.ToString(),
        "type": 'buoy',
        "GateSide": "Buoy2.png"
      }
    );
  }


  Layer.addFeatures(WP);
  Gates.push(WP);
}

const PM_HEADING = 1;
const PM_ANGLE = 2;
const PM_ORTHO = 3;
const PM_VMG = 4;
const PM_VBVMG = 5;

function SendVLMBoatWPPos(Boat, P) {
  var orderdata = {
    idu: Boat.IdBoat,
    pip: {
      targetlat: P.Lat.Value,
      targetlong: P.Lon.Value,
      targetandhdg: -1 //Boat.VLMInfo.H@WP
    }

  }

  PostBoatSetupOrder(Boat.IdBoat, 'target_set', orderdata);
}

function SendVLMBoatOrder(Mode, AngleOrLon, Lat, WPAt) {
  var request = {};;

  var verb = "pilot_set";

  if (typeof _CurPlayer == 'undefined' || typeof _CurPlayer.CurBoat == 'undefined') {
    alert("Must select a boat to send an order");
    return;
  }

  // Build WS command accoridng to required pilot mode
  switch (Mode) {
    case PM_HEADING:
    case PM_ANGLE:
      request = { idu: _CurPlayer.CurBoat.IdBoat, pim: Mode, pip: AngleOrLon };
      break;

    case PM_ORTHO:
    case PM_VBVMG:
    case PM_VMG:
      request = {
        idu: _CurPlayer.CurBoat.IdBoat,
        pim: Mode,
        pip:
        {
          targetlong: parseFloat(AngleOrLon),
          targetlat: parseFloat(Lat),
          targetandhdg: WPAt
        }
      };
      //PostBoatSetupOrder (_CurPlayer.CurBoat.IdBoat,"target_set",request);
      break;

    default:
      return;

  }

  // Post request
  PostBoatSetupOrder(_CurPlayer.CurBoat.IdBoat, verb, request);


}

function PostBoatSetupOrder(idu, verb, orderdata) {
  // Now Post the order
  $.post("/ws/boatsetup/" + verb + ".php?selectidu" + idu,
    "parms=" + JSON.stringify(orderdata),
    function (Data, TextStatus) 
    {
      if (Data.success)
      {// TODO : Force reload of boat info from server after successfull post.
        RefreshCurrentBoat(false,true);
      }
      else 
      {
        alert(GetLocalizedString("BoatSetupError") + '\n' + Data.error.code + " " + Data.error.msg)
      }
    });

}

function EngageBoatInRace(RaceID, BoatID) {
  $.post("/ws/boatsetup/race_subscribe.php",
    "parms=" + JSON.stringify(
      {
        idu: BoatID,
        idr: parseInt(RaceID)
      }
    ),
    function (data) {
      
      if (data.success)
      {
        var Msg = GetLocalizedString("youengaged")
        $("#RacesListForm").modal('hide');
        alert(Msg);
      }
      else
      {
        var Msg = data.error.msg + '\n'+ data.error.custom_error_string;
        alert(Msg);
      }
    }
  );
}


function HandleMapZoomEnd(object, element)
{
  RefreshCurrentBoat(false);
}

function LoadRankings(Boat)
{
  if ((typeof Boat === "undefined") || ! Boat || (typeof Boat.VLMInfo === "undefined")  )
  {
    return;
  }

  $.get("/ws/raceinfo/ranking.php?idr="+Boat.VLMInfo.RAC, 
        function (result)
        {
          if (result.success)
          {
            Boat.Rankings=result;
            DrawBoat(Boat,false);
          }
          else
          {
            Boat.VLMInfo.Rankings=null;
          }
        }
  );


}

function DrawOpponents(Boat,VLMBoatsLayer,BoatFeatures)
{
  if (!Boat || typeof Boat.Rankings ==="undefined" || Boat.Rankings.ranking.length ==0)
  {
    return;
  }

  for (index in Boat.Rankings.ranking )
  {
    var Opp = Boat.Rankings.ranking[index];

    if (Opp.idusers != Boat.IdBoat)
    {
      AddOpponent(Boat,VLMBoatsLayer,BoatFeatures,Opp);
    }
  }
}

function AddOpponent(Boat,Layer,Features,Opponent)
{
  var Opp_Coords = new VLMPosition(Opponent.longitude, Opponent.latitude);
  var Opp_Pos = new OpenLayers.Geometry.Point(Opp_Coords.Lon.Value, Opp_Coords.Lat.Value);
  var Opp_PosTransformed = Opp_Pos.transform(MapOptions.displayProjection, MapOptions.projection)
  var OL_Opp;

  OL_Opp = new OpenLayers.Feature.Vector(Opp_PosTransformed,
      {
        "name": Opponent.boatname,
        "Coords": Opp_Coords.ToString(),
        "type": 'opponent',
        "idboat": Opponent.idusers,
        "rank":Opponent.rank,
        "Last1h" : Opponent.last1h,
        "Last3h" : Opponent.last3h,
        "Last24h" : Opponent.last24h,
        "IsTeam" : (Opponent.country==Boat.VLMInfo.CNT)?"team":"",
        "color" : Opponent.color
      }
    );

  Layer.addFeatures(OL_Opp);
  Features.push(OL_Opp);
}

function HandleFeatureOver(e)
{ 
  var ObjType = e.feature.data.type;

  if (ObjType == "opponent")
  {
    DrawOpponentTrack(e.feature.data)
  }
  console.log("HoverOn "+ ObjType)
  /*e.feature.renderIntent = "select";
  e.feature.layer.drawFeature(e.feature);
  Console.log("Map says: Pointer entered " + e.feature.id + " on " + e.feature.layer.name);
  */
}

function HandleFeatureClick(e)
{
  // Clicking oppenent will show the track, and popup info (later)
  HandleFeatureOver(e);
}

function HandleFeatureOut(e)
{

}

function DrawOpponentTrack(FeatureData)
{
  var B = _CurPlayer.CurBoat;
  var IdBoat = FeatureData.idboat;

  if (typeof B !== "undefined" && B)
  {
    if (typeof B.OppTrack !== "undefined" && IdBoat in B.OppTrack )
    {
        B.OppTrack[IdBoat].LastShow=0;
    }
    else
    {
      var StartTime = new Date()/1000-48*3600;
      var IdRace = B.VLMInfo.RAC;
      
      $.get("/ws/boatinfo/smarttracks.php?idu="+IdBoat+"&idr="+IdRace+"&starttime="+StartTime,
            function(e)
            {
              if (e.success)
              {
                var index;

                AddBoatOppTrackPoints(B, IdBoat ,e.tracks,FeatureData.color)
                
                for (index in e.tracks_url)
                {
                  if (index > 10)
                  {
                    break;
                  }

                  $.get('/cache/tracks/'+e.tracks_url[index],
                    function (e)
                    {
                      if (e.success)
                      {
                        AddBoatOppTrackPoints(B, IdBoat ,e.tracks,FeatureData.color)
                      }
                    }
                  )
                }

              }
            }
          )
    }

    DrawBoat(B);
  }
}

function AddBoatOppTrackPoints(Boat, IdBoat, Track, TrackColor)
{

  
  if ( !(IdBoat in Boat.OppTrack))
  {
    Boat.OppTrack[IdBoat]={
      LastShow : 0,
      TrackColor : TrackColor,
      DatePos: []
    };
  }
  for (index in Track)
  {
    var Pos = Track[index];

    Boat.OppTrack[IdBoat].DatePos[Pos[0]]= {
      lat:Pos[2]/1000,
      lon:Pos[1]/1000};
  }

  
}