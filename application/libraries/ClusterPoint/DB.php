<?php
namespace ClusterPoint;

/**
* Cluster Point Database
* @author Faizan Ayubi
*/
class DB {
	
	const ACCOUNT_ID = '4219';
    const DATABASE = 'stats';
    const AUTH = 'savanramani255@gmail.com:Swift123';

	public function url() {
		$url = 'https://api-eu.clusterpoint.com/v4/' . self::ACCOUNT_ID . '/' . self::DATABASE;
		return $url;
	}

	public function create($doc) {
		$ch = curl_init();
		// https://api-eu.clusterpoint.com/v4/ACCOUNT_ID/DATABASE_NAME[ID]     -  Insert single document with explicit ID
		curl_setopt($ch, CURLOPT_URL, $this->url()); //  In this case document ID is automatically generated by system.
		curl_setopt($ch, CURLOPT_USERPWD, self::AUTH);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($doc));
		$response = curl_exec($ch);
		$errorMsg = curl_error($ch);
		if ($errorMsg) {
			var_dump($errorMsg);
		}
		curl_close($ch);
	}

	public function update($id, $doc) {
		$query = 'UPDATE ' . self::DATABASE . '["' . addslashes($id) . '"] SET ';

		foreach ($doc as $key => $value) {
			$query .= $key . ' = "' . addslashes($value) . '"';
			if($count != 1) {
				$query .= ", ";
			}
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url() . '/_query');
		curl_setopt($ch, CURLOPT_USERPWD, self::AUTH);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); // or use PATCH request  https://www.clusterpoint.com/docs/?page=Update
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
		$response = curl_exec($ch);
		$errorMsg = curl_error($ch);
		if ($errorMsg) {
			var_dump($errorMsg);
		}
		curl_close($ch);
	}

	public function delete($id) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->url() . '['.$id.']');
		curl_setopt($ch, CURLOPT_USERPWD, self::AUTH);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$response = curl_exec($ch);
		$errorMsg = curl_error($ch);
		if ($errorMsg) {
			var_dump($errorMsg);
		}
		curl_close($ch);
	}

	public function read($search = '', $field) {
		$ch = curl_init();
		$query = 'SELECT * FROM '. self::DATABASE .' ORDER BY \'timestamp\' DESC';
		// search
		if (isset($search) && $search !== '') {
		    // We are storing item text in field named "text"
			$query = 'SELECT * FROM '. self::DATABASE .' WHERE '.$field.'.CONTAINS("*'.addslashes($search).'*") ORDER BY \'timestamp\' DESC';
			// More info: https://www.clusterpoint.com/docs/?page=Matching_Documents_with_WHERE
		}
		curl_setopt($ch, CURLOPT_URL, $this->url() . '/_query'); // We added _query here!
		curl_setopt($ch, CURLOPT_USERPWD, self::AUTH);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
		$response = json_decode(curl_exec($ch));
		$errorMsg = curl_error($ch);
		if ($errorMsg) {
			var_dump($errorMsg);
		}
		curl_close($ch);

		return $response->results;
	}

	public function index($query='') {
		$ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $this->url() . '/_query'); // We added _query here!
		curl_setopt($ch, CURLOPT_USERPWD, self::AUTH);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
		$response = json_decode(curl_exec($ch));
		$errorMsg = curl_error($ch);
		if ($errorMsg) {
			var_dump($errorMsg);
		}
		curl_close($ch);

		return $response->results;
	}

}