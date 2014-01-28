CREATE OR REPLACE VIEW fittingfittedtypes AS 
 SELECT t.fittinghash, 
    (((string_agg(invtypes.typename::text, ', '::text) || ', '::text) || COALESCE(string_agg(pt.typename::text, ', '::text), ' '::text)) || ', '::text) || COALESCE(string_agg(invgroups.groupname::text, ', '::text), ' '::text) AS typelist
   FROM (        (        (         SELECT DISTINCT fittingmodules.fittinghash, 
                                    fittingmodules.typeid
                                   FROM fittingmodules
                        UNION 
                                 SELECT DISTINCT fittingcharges.fittinghash, 
                                    fittingcharges.typeid
                                   FROM fittingcharges)
                UNION 
                         SELECT DISTINCT fittingdrones.fittinghash, 
                            fittingdrones.typeid
                           FROM fittingdrones)
        UNION 
                 SELECT DISTINCT fittingimplants.fittinghash, 
                    fittingimplants.typeid
                   FROM fittingimplants) t
   JOIN eve.invtypes ON t.typeid = invtypes.typeid
   LEFT JOIN eve.invgroups ON invgroups.groupid = invtypes.groupid
   LEFT JOIN eve.invmetatypes imt ON imt.typeid = t.typeid
   LEFT JOIN eve.invtypes pt ON pt.typeid = imt.parenttypeid
  GROUP BY t.fittinghash;
