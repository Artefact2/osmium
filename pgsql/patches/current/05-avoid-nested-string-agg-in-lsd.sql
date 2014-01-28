CREATE OR REPLACE VIEW loadoutssearchdata AS 
 SELECT l.loadoutid, 
        CASE l.viewpermission
            WHEN 4 THEN accounts.accountid
            ELSE 0
        END AS restrictedtoaccountid, 
        CASE l.viewpermission
            WHEN 3 THEN 
            CASE accounts.apiverified
                WHEN true THEN accounts.corporationid
                ELSE (-1)
            END
            ELSE 0
        END AS restrictedtocorporationid, 
        CASE l.viewpermission
            WHEN 2 THEN 
            CASE accounts.apiverified
                WHEN true THEN accounts.allianceid
                ELSE (-1)
            END
            ELSE 0
        END AS restrictedtoallianceid, 
    ( SELECT fat.taglist
           FROM fittingaggtags fat
          WHERE fat.fittinghash = fittings.fittinghash) AS tags, 
    ( SELECT fft.typelist
           FROM fittingfittedtypes fft
          WHERE fft.fittinghash = fittings.fittinghash) AS modules, 
        CASE accounts.apiverified
            WHEN true THEN accounts.charactername
            ELSE accounts.nickname
        END AS author, 
    fittings.name, 
    ( SELECT fd.descriptions
           FROM fittingdescriptions fd
          WHERE fd.fittinghash = fittings.fittinghash) AS description, 
    fittings.hullid AS shipid, 
    invtypes.typename AS ship, 
    fittings.creationdate, 
    loadouthistory.updatedate, 
    ls.upvotes, 
    ls.downvotes, 
    ls.score, 
    invgroups.groupname::text AS groups, 
    fittings.evebuildnumber, 
    COALESCE(lcc.count, 0::bigint) AS comments, 
    COALESCE(lda.dps, 0::double precision) AS dps, 
    COALESCE(lda.ehp, 0::double precision) AS ehp, 
    COALESCE(lda.estimatedprice, 0::double precision) AS estimatedprice, 
    l.viewpermission
   FROM loadouts l
   JOIN loadoutslatestrevision ON l.loadoutid = loadoutslatestrevision.loadoutid
   JOIN accounts ON l.accountid = accounts.accountid
   JOIN loadouthistory ON loadouthistory.loadoutid = loadoutslatestrevision.loadoutid AND loadouthistory.revision = loadoutslatestrevision.latestrevision
   JOIN fittings ON fittings.fittinghash = loadouthistory.fittinghash
   JOIN loadoutscores ls ON ls.loadoutid = l.loadoutid
   JOIN eve.invtypes ON invtypes.typeid = fittings.hullid
   LEFT JOIN loadoutcommentcount lcc ON lcc.loadoutid = l.loadoutid
   LEFT JOIN eve.invgroups ON invgroups.groupid = invtypes.groupid
   LEFT JOIN loadoutdogmaattribs lda ON lda.loadoutid = l.loadoutid
  WHERE l.visibility = 0;
