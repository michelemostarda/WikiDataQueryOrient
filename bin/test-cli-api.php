<?php

require_once( __DIR__ . '/../lib/autoload.php' );

$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims) FROM {items[62]}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[569] AS P569) FROM {HPwTV[569:+00000001981-09-16T00:00:00Z TO +00000001981-09-17T00:00:00Z]}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[569] AS P569,claims[40] AS P40) FROM {HPwTV[569:+00000001981-09-16T00:00:00Z TO +00000001981-09-17T00:00:00Z] QUALIFY(HPwAnyV[40])}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[569] AS P569,claims[40] AS P40) FROM {HPwTV[569:+00000001981-09-16T00:00:00Z TO +00000001981-09-17T00:00:00Z] RANK(best) QUALIFY(HPwAnyV[40])}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwSomeV[20] QUALIFY(HPwAnyV[40]) LIMIT(5)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwSomeV[20,40] LIMIT(5)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwIV[31:2590631] LIMIT(5)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwIV[31:5] LIMIT(5)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwIV[31:5] QUALIFY(HPwNoV[40]) LIMIT(5)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[40][rank=best] AS P40,claims[19][rank=best] AS P19) FROM {HPwIV[31:5] LIMIT(5)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[31] AS P31) FROM {items[23] WHERE(haslinks["enwiki"])}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[31] AS P31) FROM {items[1339,350,34,64,747,24242,636,3] WHERE(haslinks["enwiki"])}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {linkedto["enwiki#Universe"]}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,sitelinks["enwiki"] AS sitelink) FROM {HPwSomeV[175,275,757] LIMIT(10)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwSomeV[1036,237] RANK(best) LIMIT(10)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwIV[31:1,2,3]}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwIV[31:1,2,3] WHERE(HPwV[41:14] OR NOT (HPwV[321:1]))}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[1082][rank=best] AS P1082) FROM {HPwQV[1082:35000 TO 60000] ASC LIMIT(10)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwQV[1082:1,35000 TO 60000] DESC LIMIT(10) RANK(best)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwSV[856:"www.whitehouse.gov","www.fafsa.edu"]}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwSV[856:"http://www.whitehouse.gov/"]}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwSV[311:"O\'reilly Pub\""] WHERE(NOT (HPwV[31:1,2,3]))}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[569] AS P569) FROM {HPwTV[569:+00000001949-01-01T00:00:00Z TO +00000001959-12-30T00:00:00Z] ASC LIMIT(10)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[569] AS P569) FROM {HPwTV[569:+00000001969-08-09T00:00:00Z TO +00000001979-08-09T00:00:00Z,+00000001980-01-01T00:00:00Z TO +00000001990-12-30T00:00:00Z] DESC LIMIT(5)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwCV[625:AROUND 38.897669444444 -77.03655 2] LIMIT(10)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwCV[625:AROUND 38.897669444444 -77.03655 2,AROUND -1.1 -2.2 3.3] RANK(best) LIMIT(5)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwIV[31:1,2,3] WHERE(HPwV[1:3.141596] OR HPwV[353:2525])}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwIV[31:1,2,3] RANK(preferred) QUALIFY(HPwV[15141:222,252,353])}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwIV[31:1,2,3] QUALIFY(HPwV[1414:89 TO 90,1] AND HPwV[1414:356,46]) WHERE(HPwV[14:3.0] OR (HPwV[24:2.5] AND HPwV[98:5.6]))}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM INTERSECT( {HPwIV[31:1,2,3]} {HPwCV[131:AROUND 1.1 -2.2 3.3,AROUND -1.1 -2.2 3.3]} )';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM DIFFERENCE( {HPwIV[31:1,2,3]} {HPwCV[131:AROUND 1.1 -2.2 3.3,AROUND -1.1 -2.2 3.3]} )';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM UNION( {HPwIV[31:1,2,3]} {HPwCV[131:AROUND 1.1 -2.2 3.3,AROUND -1.1 -2.2 3.3]} )';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM UNION( {HPwIV[31:1,2,3]} INTERSECT( {HPwSV[311:"cat","says","meow"]} {HPwQV[31:1.0,2,33 TO 63]} ) )';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[40] AS P40) FROM {HPwIVWeb[23505] OUT[40] RANK(best)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[40] AS P40) FROM {HPwIVWeb[23505] IN[40] RANK(best)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link,claims[40] AS P40) FROM {HPwIVWeb[23505] OUT[40] IN[40] RANK(best)}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwIVWeb[30] OUT[150] IN[17,131]}';
$q[] = '(id,labels["en"] AS label,sitelinks["enwiki"] AS link) FROM {HPwIVWeb[$CHILDREN] OUT[40]} GIVEN($CHILDREN = {HPwIV[40:23505]})';

foreach ( $q as $query ) {
	print( $query . "\n=====\n" );
	print( WdqQueryParser::parse( "$query" ) . "\n\n" );
}
