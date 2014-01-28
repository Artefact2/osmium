CREATE OR REPLACE VIEW loadoutscores AS 
 SELECT l.loadoutid, 
    COALESCE(uv.count, 0::bigint) AS upvotes, 
    COALESCE(dv.count, 0::bigint) AS downvotes, 
    ((COALESCE(uv.count::numeric, 0.5) + 1.9208) / (COALESCE(uv.count::numeric, 0.5) + COALESCE(dv.count, 0::bigint)::numeric) - 1.96 * sqrt(COALESCE(uv.count::numeric, 0.5) * COALESCE(dv.count, 0::bigint)::numeric / (COALESCE(uv.count::numeric, 0.5) + COALESCE(dv.count, 0::bigint)::numeric) + 0.9604) / (COALESCE(uv.count::numeric, 0.5) + COALESCE(dv.count, 0::bigint)::numeric)) / (1::numeric + 3.8416 / (COALESCE(uv.count::numeric, 0.5) + COALESCE(dv.count, 0::bigint)::numeric)) AS score
   FROM loadouts l
   LEFT JOIN votecount uv ON uv.type = 1 AND uv.targettype = 1 AND uv.targetid1 = l.loadoutid AND uv.targetid2 IS NULL AND uv.targetid3 IS NULL
   LEFT JOIN votecount dv ON dv.type = 2 AND dv.targettype = 1 AND dv.targetid1 = l.loadoutid AND dv.targetid2 IS NULL AND dv.targetid3 IS NULL;
