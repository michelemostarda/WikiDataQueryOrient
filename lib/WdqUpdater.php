<?php

class WdqUpdater {
	/** @var MultiHttpClient */
	protected $http;

	/** @var string */
	protected $url;
	/** @var string */
	protected $user;
	/** @var string */
	protected $password;

	/** @var string */
	protected $sessionId;

	/** @var array ENUM map for short integer field */
	protected static $rankMap = array(
		'preferred'  => 1,
		'normal'     => 0,
		'deprecated' => -1
	);

	/**
	 * @param MultiHttpClient $http
	 * @param array $auth
	 */
	public function __construct( MultiHttpClient $http, array $auth ) {
		$this->http = $http;
		$this->url = $auth['url'];
		$this->user = $auth['user'];
		$this->password = $auth['password'];
	}

	/**
	 * See http://www.mediawiki.org/wiki/Wikibase/DataModel/Primer
	 *
	 * @param array $entities
	 * @param string $update (update/insert/upsert)
	 * @throws Exception
	 */
	public function importEntities( array $entities, $update ) {
		$sqlQueries = array();
		foreach ( $entities as $entity ) {
			if ( $entity['type'] === 'item' ) {
				$sqlQueries[] = $this->importItemVertexSQL( $entity, $update );
			} elseif ( $entity['type'] === 'property' ) {
				$sqlQueries[] = $this->importPropertyVertexSQL( $entity, $update );
			} else {
				trigger_error( "Unrecognized entity of type '{$entity['type']}'." );
			}
		}

		$this->tryCommand( $sqlQueries, false, true );
	}

	/**
	 * See http://www.mediawiki.org/wiki/Wikibase/DataModel/Primer
	 *
	 * @param array $item
	 * @param string $update (update/insert/upsert)
	 * @return string
	 */
	protected function importItemVertexSQL( array $item, $update ) {
		$siteLinks = array(); // map of (<site> => <site>#<title>)
		// Flatten site links to a 1-level list for indexing
		if ( isset( $item['sitelinks'] ) ) {
			foreach ( $item['sitelinks'] as $site => $link ) {
				$siteLinks[$site] = $link['site'] . '#' . $link['title'];
			}
		}
		$labels = array(); // map of (<language> => <label>)
		// Flatten labels to a 1-level list for querying
		if ( isset( $item['labels'] ) ) {
			foreach ( $item['labels'] as $lang => $label ) {
				$labels[$lang] = $label['value'];
			}
		}

		$coreItem = array(
			'id'        => (float) WdqUtils::wdcToLong( $item['id'] ),
			'labels'    => $labels ? (object)$labels : (object)array(),
			'sitelinks' => $siteLinks ? (object)$siteLinks : (object)array(),
		);

		if ( isset( $item['claims'] ) ) {
			// Include simplified claims for easy filtering/selecting
			$coreItem['claims'] = $this->getSimpliedClaims( $item['claims'] );
			// Include the property IDs (pids) referenced for tracking
			$coreItem += $this->getReferenceIdSet( $item['claims'] );
		}

		if ( $update === 'update' || $update === 'upsert' ) {
			// Don't use CONTENT; https://github.com/orientechnologies/orientdb/issues/3176
			$set = $this->sqlSet( $coreItem );
			return "update Item set $set where id={$coreItem['id']}";
		}

		if ( $update === 'insert' || $update === 'upsert' ) {
			return "create vertex Item content " . WdqUtils::toJSON( $coreItem );
		}

		throw new Exception( "Bad method '$update'." );
	}

	/**
	 * Get a streamlined version of $claims
	 *
	 * @param array $claims
	 * @return array
	 */
	protected function getSimpliedClaims( array $claims ) {
		$sClaims = array();

		foreach ( $claims as $propertyId => $statements ) {
			$pId = WdqUtils::wdcToLong( $propertyId );

			$sClaims[$pId] = array();

			// http://www.wikidata.org/wiki/Help:Ranking
			$maxRank = -1; // highest statement rank for property
			foreach ( $statements as $statement ) {
				$maxRank = max( $maxRank, self::$rankMap[$statement['rank']] );
			}

			foreach ( $statements as $statement ) {
				$sClaim = $this->getSimpleSnak( $statement['mainsnak'] );
				$sClaim['rank'] = self::$rankMap[$statement['rank']];
				$sClaim['best'] = self::$rankMap[$statement['rank']] >= $maxRank ? 1 : 0;

				$qlfrs = isset( $statement['qualifiers'] ) ? $statement['qualifiers'] : array();
				foreach ( $qlfrs as $qPropertyId => $qSnaks ) {
					$qPId = (string) WdqUtils::wdcToLong( $qPropertyId );
					$sClaim['qlfrs'][$qPId] = array();
					foreach ( $qSnaks as $qSnak ) {
						$sClaim['qlfrs'][$qPId][] = $this->getSimpleSnak( $qSnak );
					}
				}

				$sClaims[$pId][] = $sClaim;
			}

			// Sort the statements by descending rank
			usort( $sClaims[$pId], function( $a, $b ) {
				if ( $a['rank'] == $b['rank'] ) {
					return 0;
				}
				return ( $a['rank'] > $b['rank'] ) ? -1 : 1;
			} );
		}

		return $sClaims;
	}

	/**
	 * @param array $snak
	 * @return array
	 */
	protected function getSimpleSnak( array $snak ) {
		$simpleSnak = array( 'snaktype' => $snak['snaktype'] );

		if ( $snak['snaktype'] === 'value' ) {
			$valueType = $snak['datavalue']['type'];

			$dataValue = null;
			if ( $valueType === 'wikibase-entityid' ) {
				$dataValue = $snak['datavalue']['value']['numeric-id'];
			} elseif ( $valueType === 'time' ) {
				$dataValue = $snak['datavalue']['value']['time'];
			} elseif ( $valueType === 'quantity' ) {
				$dataValue = (float)$snak['datavalue']['value']['amount'];
			} elseif ( $valueType === 'globecoordinate' ) {
				$dataValue = array(
					'lat' => $snak['datavalue']['value']['latitude'],
					'lon' => $snak['datavalue']['value']['longitude']
				);
			} elseif ( $valueType === 'url' || $valueType === 'string' ) {
				$dataValue = (string) $snak['datavalue']['value'];
			}

			$simpleSnak['valuetype'] = $valueType;
			$simpleSnak['datavalue'] = $dataValue;
		}

		return $simpleSnak;
	}

	/**
	 * Get IDs of items and properties refered to by $claims
	 *
	 * @param array $claims
	 * @return array
	 */
	protected function getReferenceIdSet( array $claims ) {
		$refs = array( 'pids' => array(), 'iids' => array() );

		foreach ( $claims as $propertyId => $statements ) {
			$pId = WdqUtils::wdcToLong( $propertyId );
			$refs['pids'][] = (float)$pId;
			foreach ( $statements as $statement ) {
				$mainSnak = $statement['mainsnak'];
				if ( $mainSnak['snaktype'] === 'value' &&
					$mainSnak['datavalue']['type'] === 'wikibase-entityid'
				) {
					$refs['iids'][] = (float)$mainSnak['datavalue']['value']['numeric-id'];
				}
			}
		}

		// Embedded sets do not allow duplicates
		$refs['iids'] = array_values( array_unique( $refs['iids'] ) );

		return $refs;
	}

	/**
	 * @param array $item
	 * @param string $update (insert/update/upsert)
	 * @return string
	 */
	protected function importPropertyVertexSQL( array $item, $update ) {
		$coreItem = array(
			'id'       => (float) WdqUtils::wdcToLong( $item['id'] ),
			'datatype' => $item['datatype']
		);

		if ( $update === 'update' || $update === 'upsert' ) {
			// Don't use CONTENT; https://github.com/orientechnologies/orientdb/issues/3176
			$set = $this->sqlSet( $coreItem );
			return "update Property set $set where id={$coreItem['id']}";
		}

		if ( $update === 'insert' || $update === 'upsert' ) {
			return "create vertex Property content " . WdqUtils::toJSON( $coreItem );
		}

		throw new Exception( "Bad method '$update'." );
	}

	/**
	 * See http://www.mediawiki.org/wiki/Wikibase/DataModel
	 * See https://www.wikidata.org/wiki/Wikidata:Glossary
	 *
	 * @param array $item
	 * @param string $method (rebuild/bulk)
	 * @param array|null $classes Only do certain edge classes
	 */
	public function importItemPropertyEdges( array $item, $method, array $classes = null ) {
		if ( !isset( $item['claims'] ) ) {
			return; // nothing to do
		} elseif ( $classes !== null && !count( $classes ) ) {
			return; // nothing to do
		}

		$qId = WdqUtils::wdcToLong( $item['id'] );

		$maxRankByPid = array(); // map of (pid => rank)
		foreach ( $item['claims'] as $propertyId => $statements ) {
			$pId = WdqUtils::wdcToLong( $propertyId );
			foreach ( $statements as $statement ) {
				$rank = self::$rankMap[$statement['rank']];
				$maxRankByPid[$pId] = isset( $maxRankByPid[$pId] )
					? max( $maxRankByPid[$pId], $rank )
					: $rank;
			}
		}

		$newEdgeSids = array(); // map of (sid => 1)
		$dvEdges = array(); // list of data value statements (maps with class/val/rank)
		foreach ( $item['claims'] as $propertyId => $statements ) {
			$pId = WdqUtils::wdcToLong( $propertyId );
			foreach ( $statements as $statement ) {
				$mainSnak = $statement['mainsnak'];

				$edges = array();
				if ( $mainSnak['snaktype'] === 'value' ) {
					$edges = $this->getValueStatementEdges( $qId, $pId, $mainSnak );
				} elseif ( $mainSnak['snaktype'] === 'somevalue' ) {
					$edges[] = array(
						'class'   => 'HPwSomeV',
						'oid'     => $qId,
						'iid'     => $pId,
						'toClass' => 'Property'
					);
				} elseif ( $mainSnak['snaktype'] === 'novalue' ) {
					$edges[] = array(
						'class'   => 'HPwNoV',
						'oid'     => $qId,
						'iid'     => $pId,
						'toClass' => 'Property'
					);
				}

				// https://www.wikidata.org/wiki/Help:Ranking
				foreach ( $edges as &$edge ) {
					$edge['rank'] = self::$rankMap[$statement['rank']];
					$edge['best'] = $edge['rank'] >= $maxRankByPid[$pId] ? 1 : 0;
					$edge['sid'] = $statement['id'];
					$edge['qlfrs'] = isset( $statement['qualifiers'] )
						? (object)$statement['qualifiers']
						: (object)array();
					$newEdgeSids[$edge['sid']] = 1;
				}
				unset( $edge );

				$dvEdges = array_merge( $dvEdges, $edges );
			}
		}

		$sqlQueries = array();

		// Delete obsolete outgoing edges...
		$existingEdgeSids = array(); // map of (sid => #RID)
		if ( $method !== 'bulk_init' ) {
			// Get the prior edges SIDs/#RIDs
			$res = $this->tryQuery(
				"select sid,@RID from (select expand(outE()) from Item where id=$qId)" );
			foreach ( $res as $record ) {
				$existingEdgeSids[$record['sid']] = $record['RID'];
			}
			$deleteSids = array_diff_key( $existingEdgeSids, $newEdgeSids );
			// Destroy any prior outgoing edges with obsolete SIDs
			foreach ( $deleteSids as $sid => $rid ) {
				$sql = "delete edge $rid";
				if ( $classes ) {
					$sql .= ' where @class in [' . implode( ',', $classes ) . ']';
				}
				$sqlQueries[] = $sql;
			}
		}

		// Create/update all of the new outgoing edges...
		foreach ( $dvEdges as $dvEdge ) {
			if ( $classes && !in_array( $dvEdge['class'], $classes ) ) {
				continue; // skip this edge class
			}
			$class = $dvEdge['class'];
			unset( $dvEdge['class'] );
			$toClass = $dvEdge['toClass'];
			unset( $dvEdge['toClass'] );

			if ( isset( $existingEdgeSids[$dvEdge['sid']] ) ) {
				// If an edge was found with the SID, then update it...
				$rid = $existingEdgeSids[$dvEdge['sid']];
				$set = $this->sqlSet( $dvEdge );
				$sqlQueries[] = "update $rid set $set";
			} else {
				// If no edge was found with the SID, then make a new one...
				$sqlQueries[] =
					"create edge $class " .
					"from (select from Item where id=$qId) " .
					"to (select from $toClass where id={$dvEdge['iid']}) content " .
					WdqUtils::toJSON( $dvEdge );
			}
		}

		$this->tryCommand( $sqlQueries, false );
	}

	/**
	 * See http://www.wikidata.org/wiki/Special:ListDatatypes
	 *
	 * @param integer $qId 64-bit integer
	 * @param integer $pId 64-bit integer
	 * @param array $mainSnak
	 * @return array
	 */
	protected function getValueStatementEdges( $qId, $pId, array $mainSnak ) {
		$dvEdges = array();

		$type = $mainSnak['datavalue']['type'];
		if ( $type === 'wikibase-entityid' ) {
			$otherId = $mainSnak['datavalue']['value']['numeric-id'];
			$dvEdges[] = array(
				'class'   => 'HPwIV',
				'val'     => $otherId,
				'oid'     => $qId,
				'iid'     => $pId,
				'toClass' => 'Property'
			);
			$dvEdges[] = array(
				'class'   => 'HIaPV',
				'pid'     => $pId,
				'oid'     => $qId,
				'iid'     => $otherId,
				'toClass' => 'Item'
			);
		} elseif ( $type === 'time' ) {
			$time = $mainSnak['datavalue']['value']['time'];
			$tsUnix = WdqUtils::getUnixTimeFromISO8601( $time ); // for range queries
			if ( $tsUnix !== false ) {
				$dvEdges[] = array(
					'class'   => 'HPwTV',
					'val'     => $tsUnix,
					'oid'     => $qId,
					'iid'     => $pId,
					'toClass' => 'Property'
				);
			}
		} elseif ( $type === 'quantity' ) {
			$amount = $mainSnak['datavalue']['value']['amount']; // decimals
			$dvEdges[] = array(
				'class'   => 'HPwQV',
				'val'     => (float) $amount,
				'oid'     => $qId,
				'iid'     => $pId,
				'toClass' => 'Property'
			);
		} elseif ( $type === 'globecoordinate' ) {
			$dvEdge = WdqUtils::normalizeGeoCoordinates( array(
				'class'   => 'HPwCV',
				'lat'     => (float) $mainSnak['datavalue']['value']['latitude'],
				'lon'     => (float) $mainSnak['datavalue']['value']['longitude'],
				'oid'     => $qId,
				'iid'     => $pId,
				'toClass' => 'Property'
			) );
			if ( $dvEdge ) {
				$dvEdges[] = $dvEdge;
			}
		} elseif ( $type === 'url' || $type === 'string' ) {
			$dvEdges[] = array(
				'class'   => 'HPwSV',
				'val'     => (string) $mainSnak['datavalue']['value'],
				'oid'     => $qId,
				'iid'     => $pId,
				'toClass' => 'Property'
			);
		}

		return $dvEdges;
	}

	/**
	 * @param string|int|array $ids 64-bit integers
	 */
	public function deleteItemVertexes( $ids ) {
		// https://github.com/orientechnologies/orientdb/issues/3150
		$orClause = array();
		foreach ( (array)$ids as $id ) {
			$orClause[] = "id='$id'";
		}
		$orClause = implode( ' OR ', $orClause );
		$this->tryCommand( "delete vertex Item where ($orClause)" );
	}

	/**
	 * @param string|int|array $ids 64-bit integers
	 */
	public function deletePropertyVertexes( $ids ) {
		// https://github.com/orientechnologies/orientdb/issues/3150
		$orClause = array();
		foreach ( (array)$ids as $id ) {
			$orClause[] = "id='$id'";
		}
		$orClause = implode( ' OR ', $orClause );
		$this->tryCommand( "delete vertex Property where ($orClause)" );
	}

	/**
	 * @param string|array $sql
	 * @param bool $atomic
	 * @param bool $ignore_dups
	 * @throws Exception
	 */
	public function tryCommand( $sql, $atomic = true, $ignore_dups = true ) {
		$sql = (array)$sql;
		if ( !$sql ) {
			return; // nothing to do
		}

		$ops = array();
		foreach ( $sql as $sqlCmd ) {
			$ops[] = array( 'type' => 'cmd', 'language' => 'sql', 'command' => $sqlCmd );
		}

		$req = array(
			'method'  => 'POST',
			'url'     => "{$this->url}/batch/WikiData",
			'headers' => array(
				'Content-Type' => "application/json",
				'Cookie'       => "OSESSIONID={$this->getSessionId()}" ),
			'body'    => json_encode( array(
				'transaction' => $atomic,
				'operations'  => $ops
			) )
		);

		list( $rcode, $rdesc, $rhdrs, $rbody, $rerr ) = $this->http->run( $req );
		// Retry once for random failures (or when the payload is too big)...
		if ( $rcode != 200 && count( $sql ) > 1 ) {
			if ( $atomic ) {
				print( "Retrying batch command.\n" );
				list( $rcode, $rdesc, $rhdrs, $rbody, $rerr ) = $this->http->run( $req );
			} else {
				print( "Retrying each batch command.\n" );
				// Break down the commands if possible, which gets past some failures
				foreach ( $sql as $sqlCmd ) {
					$this->tryCommand( $sqlCmd, true, $ignore_dups );
				}
				return;
			}
		}

		if ( $rcode != 200 ) {
			if ( $ignore_dups && strpos( $rbody, 'ORecordDuplicatedException' ) !== false ) {
				return;
			}
			$errSql = is_array( $sql ) ? implode( "\n", $sql ) : $sql;
			print( "Error on command:\n$errSql\n\n" );
			throw new Exception( "Command failed ($rcode). Got:\n$rbody" );
		}

		return;
	}

	/**
	 * @param string|array $sql
	 * @return array
	 * @throws Exception
	 */
	public function tryQuery( $sql ) {
		list( $rcode, $rdesc, $rhdrs, $rbody, $rerr ) = $this->http->run( array(
			'method'  => 'GET',
			'url'     => "{$this->url}/query/WikiData/sql/" . rawurlencode( $sql ),
			'headers' => array( 'Cookie' => "OSESSIONID={$this->getSessionId()}" )
		) );

		if ( $rcode != 200 ) {
			$tsql = substr( $sql, 0, 255 );
			throw new Exception( "Command failed ($rcode).\n\nSent:\n$tsql...\n\nGot:\n$rbody" );
		}

		$response = json_decode( $rbody, true );
		if ( $response === null ) {
			$tsql = substr( $sql, 0, 255 );
			throw new Exception( "Bad JSON response.\n\nSent:\n$tsql...\n\nGot:\n$rbody" );
		}

		return $response['result'];
	}

	/**
	 * @return string
	 * @throws Exception
	 */
	protected function getSessionId() {
		if ( $this->sessionId !== null ) {
			return $this->sessionId;
		}
		$hash = base64_encode( "{$this->user}:{$this->password}" );
		list( $rcode, $rdesc, $rhdrs, $rbody, $rerr ) = $this->http->run( array(
			'method'  => 'GET',
			'url'     => "{$this->url}/connect/WikiData",
			'headers' => array( 'Authorization' => "Basic " . $hash )
		) );
		$m = array();
		if ( preg_match( '/(?:^|;)OSESSIONID=([^;]+);/', $rhdrs['set-cookie'], $m ) ) {
			$this->sessionId = $m[1];
		} else {
			throw new Exception( "Invalid authorization credentials ($rcode).\n" );
		}

		return $this->sessionId;
	}

	/**
	 * @param array $object
	 * @return string
	 */
	protected function sqlSet( array $object ) {
		$set = array();
		foreach ( $object as $key => $value ) {
			if ( is_float( $value ) || is_int( $value ) ) {
				$set[] = "$key=$value";
			} elseif ( is_scalar( $value ) ) {
				// https://github.com/orientechnologies/orientdb/issues/2424
				$value = str_replace( "\n", " ", $value );
				$set[] = "$key='" . addcslashes( $value, "'" ) . "'";
			} else {
				$set[] = "$key=" . WdqUtils::toJSON( $value );
			}
		}
		return implode( ', ', $set );
	}
}
