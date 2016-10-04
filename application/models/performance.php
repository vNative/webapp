<?php

/**
 * @author Faizan Ayubi
 */
use Shared\Utils as Utils;
class Performance extends Shared\Model {

    /**
     * @column
     * @readwrite
     * @type mongoid
     * @index
     */
    protected $_user_id;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_impressions = 0;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_clicks = 0;

    /**
     * @column
     * @readwrite
     * @type integer
     */
    protected $_conversions = 0;

    /**
     * @column
     * @readwrite
     * @type text
     */
    protected $_cpc;

    /**
     * @column
     * @readwrite
     * @type decimal
     * @length 10,2
     */
    protected $_revenue = 0.0;

    public static function calculate($user, $dateQuery = []) {
        $query = ['user_id' => $user->_id];
        $both = isset($dateQuery['start']) && isset($dateQuery['end']);
        if ($both) {
            $query['created'] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];
        }
        $perf = self::all($query, ['clicks', 'revenue']);

        $clicks = 0; $revenue = 0.00;
        foreach ($perf as $p) {
            $clicks += $p->clicks;
            $revenue += $p->revenue;
        }
        return [
            'clicks' => $clicks,
            'revenue' => $revenue
        ];
    }

    public static function exists($user, $date) {
        $dateQuery = Utils::dateQuery(['start' => $date, 'end' => $date]);
        $perf = self::first([
            'user_id' => $user->_id,
            'created' => ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']]
        ]);
        if (!$perf) {
            $perf = new self([
                'user_id' => $user->_id,
                'created' => $date
            ]);
        }
        $perf->clicks = 0; $perf->impressions = 0;
        $perf->revenue = 0.00;
        $perf->cpc = 0.00; $perf->conversions = 0;
        return $perf;
    }

    public function update($data = []) {
        $fields = $this->getColumns();
        foreach ($fields as $key => $value) {
            if (isset($data[$key])) {
                $this->$key += $data[$key];
            }
        }
        $this->calAvgCpc();
    }

    public function calAvgCpc() {
        if ($this->clicks === 0) {
            $avgCpc = 0.00;
        } else {
            $avgCpc = $this->revenue / $this->clicks;
        }

        $this->cpc = round($avgCpc, 6);
    }

    public static function overall($dateQuery = [], $user=null) {
        $q = [];$clicks = []; $impressions = []; $payouts = []; $total_clicks = 0; $total_payouts = 0; $total_impressions = 0;

        if (is_array($user)) {
            $in = [];
            foreach ($user as $u) {
                $in[] = $u->_id;
            }
            $q["user_id"] = ['$in' => $in];
        } elseif ($user) {
            $q["user_id"] = $user->id;
        }

        if (count($dateQuery) > 0) {
            $q["created"] = ['$gte' => $dateQuery['start'], '$lte' => $dateQuery['end']];   
        }
        $performances = self::all($q, ['revenue', 'clicks', 'created', 'impressions']);
        foreach ($performances as $p) {
            //calculating datewise
            $date = date('Y-m-d', $p->created->getTimestamp());

            $total_clicks += $p->clicks;
            if (array_key_exists($date, $clicks)) {
                $clicks[$date] += $p->clicks;
            } else {
                $clicks[$date] = $p->clicks;
            }

            $total_impressions += $p->impressions;
            if (array_key_exists($date, $impressions)) {
                $impressions[$date] += $p->impressions;
            } else {
                $impressions[$date] = $p->impressions;
            }

            $total_payouts += $p->revenue;
            if (array_key_exists($date, $payouts)) {
                $payouts[$date] += $p->revenue;
            } else {
                $payouts[$date] = $p->revenue;
            }
        }

        ksort($clicks); ksort($impressions); ksort($payouts);
        $clicks = Utils::dateArray($clicks);
        $impressions = Utils::dateArray($impressions);
        $payouts = Utils::dateArray($payouts);

        return [
            "impressions" => $impressions,
            "total_impressions" => $total_impressions,
            "clicks" => $clicks,
            "total_clicks" => $total_clicks,
            "payouts" => $payouts,
            "total_payouts" => $total_payouts
        ];
    }
}
