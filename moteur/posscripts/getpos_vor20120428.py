#!/usr/bin/env python
# -*- coding: utf-8 -*-

import urllib
import sys, zipfile, os
import time
import xml.etree.ElementTree as ElementTree


import getposlib as gp
import sys, time

#Generate with basedatas
def baseboat(rid):
    vlmboatidfirst = 1200
    return {'vlmid' : -vlmboatidfirst-int(rid)}
    
vlmidrace = 20120422
vlmusernameprefix = "VOR2012_"
basefilename = "vor%d" % vlmidrace
geotree = gp.GeovoileTree("http://volvooceanrace.geovoile.com/2011/shared/data/leg6.static.hwz", basefilename)
coordfactor = geotree.factors()

timezero = 1335114000 #geotree.timezero() #FIXME (22 apr 2012 - 17:00:00 GMT)
#timezero = 1332032400 #geotree.timezero() #FIXME

boats = geotree.boats()

geotree = gp.GeovoileTree("http://volvooceanrace.geovoile.com/2011/shared/data/leg6.update.hwz", basefilename+"update")

for rid in boats.keys() :
    bb = baseboat(int(rid))
    boats[rid]['boatid'] = rid
    boats[rid]['vlmid'] = -bb['vlmid']
    boats[rid]['vlmboatname'] = "%03d - %s" % (rid, boats[rid]['name'])
    boats[rid]['vlmusername'] = "%s%03d" % (vlmusernameprefix, rid)

for track in geotree.tracks(tagid='id'):
      #20091108|1|1257681600|-729|BT|Sébastien Josse - Jean François Cuzon|50.016000|-1.891500|85.252725|4651.600000
     rid, t, lat, lon = track
     t += timezero
     if time.time() - t < 48*3600 and t < time.time():
         print "%d|0|%d|%d|%s|BAR|%f|%f|0.|0." % (vlmidrace, t, -boats[rid]['vlmid'], boats[rid]['name'].encode('utf8'), lat/coordfactor, lon/coordfactor)

