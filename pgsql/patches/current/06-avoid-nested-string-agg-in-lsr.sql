CREATE OR REPLACE VIEW loadoutssearchresults AS 
 SELECT loadouts.loadoutid, 
    loadouts.privatetoken, 
    loadoutslatestrevision.latestrevision, 
    loadouts.viewpermission, 
    loadouts.visibility, 
    fittings.hullid, 
    invtypes.typename, 
    fittings.creationdate, 
    loadouthistory.updatedate, 
    fittings.name, 
    fittings.evebuildnumber, 
    accounts.nickname, 
    accounts.apiverified, 
    accounts.charactername, 
    accounts.characterid, 
    accounts.corporationname, 
    accounts.corporationid, 
    accounts.alliancename, 
    accounts.allianceid, 
    loadouts.accountid, 
    ( SELECT fat.taglist
           FROM fittingaggtags fat
          WHERE fat.fittinghash = fittings.fittinghash) AS taglist, 
    accounts.reputation, 
    loadoutupdownvotes.votes, 
    loadoutupdownvotes.upvotes, 
    loadoutupdownvotes.downvotes, 
    COALESCE(lcc.count, 0::bigint) AS comments, 
    lda.dps, 
    lda.ehp, 
    lda.estimatedprice
   FROM loadouts
   JOIN loadoutslatestrevision ON loadouts.loadoutid = loadoutslatestrevision.loadoutid
   JOIN loadouthistory ON loadoutslatestrevision.latestrevision = loadouthistory.revision AND loadouthistory.loadoutid = loadouts.loadoutid
   JOIN fittings ON fittings.fittinghash = loadouthistory.fittinghash
   JOIN accounts ON accounts.accountid = loadouts.accountid
   JOIN eve.invtypes ON fittings.hullid = invtypes.typeid
   JOIN loadoutupdownvotes ON loadoutupdownvotes.loadoutid = loadouts.loadoutid
   LEFT JOIN loadoutcommentcount lcc ON lcc.loadoutid = loadouts.loadoutid
   LEFT JOIN loadoutdogmaattribs lda ON lda.loadoutid = loadouts.loadoutid;
