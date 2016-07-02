//
// Position class
//
// Formating, conversion, and geo computation
//
//

const POS_FORMAT_DEFAULT=0;
// Earth radius for all calculation of distance in Naut. Miles
const EARTH_RADIUS  = 3443.84;
        

function Deg2Rad(v)
{
    return v/180.0*Math.PI;
}

function Rad2Deg(v)
{
    return v/Math.PI*180.0;
}


// Constructor
function Position(lon, lat,  format=POS_FORMAT_DEFAULT)
{

    if (typeof format == 'undefined' || format == POS_FORMAT_DEFAULT)
    {
        // Default constructor, lon and lat in degs flaoting format
        this.Lon=new Coords(lon,1);
        this.Lat=new Coords(lat,0);
    }

    // Default string formating
    this.ToString=function()
    {
        return this.Lat.ToString() + " " + this.Lon.ToString();
    }
    
    // function GetLoxoDist
    // Returns the loxodromic distance to another point
    this.GetLoxoDist= function(P)
    {

        var Lat1  = Deg2Rad(this.Lat.Value)
        var Lat2  = Deg2Rad(P.Lat.Value);
        var Lon1  = -Deg2Rad(this.Lon.Value)
        var Lon2  = -Deg2Rad(P.Lon.Value)

        var TOL  = 0.000000000000001
        var d =0;
        var q=0;
        if (Math.abs(Lat2 - Lat1) < Math.sqrt(TOL)) 
        {
            q = Math.cos(Lat1)
        }
        else
        {
        	 q = (Lat2 - Lat1) / Math.log(Math.tan(Lat2 / 2 + Math.PI / 4) / Math.tan(Lat1 / 2 +Math.PI / 4))
        }

        d= Math.sqrt(Math.pow(Lat2 - Lat1, 2) + q * q * (Lon2 - Lon1) * (Lon2 - Lon1) )
        return EARTH_RADIUS *d;
    }

    // Compute the position of point at r * distance to point P
    // Along loxodrome from this to P
    this.ReachDistLoxo = function(P, r)
    {

        var d = this.GetLoxoDist(P)/EARTH_RADIUS*r;
       
        var Lat1  = Deg2Rad(this.Lat.Value);
        var Lon1  = -Deg2Rad(this.Lon.Value);
        var tc  = Deg2Rad(this.GetLoxoCourse(P));
        var Lat =0; 
        var Lon =0;
        var TOL  = 0.000000000000001;
        var q =0;
        var dPhi =0;
        var dlon =0;

        Lat = Lat1 + d * Math.cos(tc);
        if (Math.abs(Lat) > Math.PI / 2) 
        {
            //'"d too large. You can't go this far along this rhumb line!"
            throw "Invalid distance, can't go that far"
        }

        if (Math.abs(Lat - Lat1) < Math.sqrt(TOL))
        {
            q = Math.cos(Lat1)
        }
        else
        {
            dPhi = Math.log(Math.tan(Lat / 2 + Math.PI / 4) / Math.tan(Lat1 / 2 +Math.PI / 4))
            q = (Lat - Lat1) / dPhi
        }
        dlon = -d * Math.sin(tc) / q
        Lon = -(((Lon1 + dlon +Math.PI) % (2 *Math.PI) - Math.PI));

        return new Position(Rad2Deg(Lon),Rad2Deg(Lat));


    }

    //
    // Return loxodromic course from this to P in °
    //
    this.GetLoxoCourse = function(P)
    {
        var Lon1  = -Deg2Rad(this.Lon.Value)
        var Lon2  = -Deg2Rad(P.Lon.Value)
        var Lat1  = Deg2Rad(this.Lat.Value)
        var Lat2  = Deg2Rad(P.Lat.Value)

        /*if (Lon1 > 0)
        {
            Lon2 += 2 * Math.PI
        }
        else
        {   
            Lon2 -= 2 * Math.PI
        }*/
        var dlon_w  = (Lon2 - Lon1) % (2 * Math.PI)
        var dlon_e  = (Lon1 - Lon2) % (2 * Math.PI)
        var dphi  = Math.log(Math.tan(Lat2 / 2 + Math.PI / 4) / Math.tan(Lat1 / 2 + Math.PI / 4))
        var tc 

        
        if (dlon_w < dlon_e) 
        { // Westerly rhumb line is the shortest
            tc = Math.atan2(dlon_w, dphi) % (2 * Math.PI)
            
        }
        else
        {
            tc = Math.atan2(-dlon_e, dphi) % (2 * Math.PI)
           

        }

        var ret  = (720 - (tc / Math.PI * 180)) % 360;

        return ret
    }
}




