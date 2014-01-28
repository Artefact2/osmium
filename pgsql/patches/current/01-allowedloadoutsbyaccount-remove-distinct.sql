CREATE OR REPLACE VIEW allowedloadoutsbyaccount AS 
 SELECT a.accountid, 
    l.loadoutid
   FROM loadouts l
   JOIN accounts author ON author.accountid = l.accountid
   LEFT JOIN contacts c ON author.apiverified = true AND author.accountid = c.accountid AND (l.viewpermission = ANY (ARRAY[5, 6]))
   JOIN accounts a ON l.viewpermission = 0 OR l.viewpermission = 1 OR l.viewpermission = 2 AND a.apiverified = true AND author.apiverified = true AND a.allianceid = author.allianceid OR l.viewpermission = 3 AND a.apiverified = true AND author.apiverified = true AND a.corporationid = author.corporationid OR a.accountid = author.accountid OR l.viewpermission = 5 AND (a.allianceid = author.allianceid OR a.corporationid = author.corporationid OR c.standing > 0::double precision AND (c.contactid = a.characterid OR c.contactid = a.corporationid OR c.contactid = a.allianceid)) OR l.viewpermission = 6 AND (a.allianceid = author.allianceid OR a.corporationid = author.corporationid OR c.standing > 5::double precision AND (c.contactid = a.characterid OR c.contactid = a.corporationid OR c.contactid = a.allianceid));
