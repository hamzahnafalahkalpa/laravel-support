<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

trait HasActivity
{
    public function activity()
    {
        return $this->morphOneModel('Activity', 'reference');
    }

    public function pushActivity(string $flag, mixed $activity_status)
    {
        $activity = $this->activity()->firstOrCreate([
            'activity_flag'  => $flag,
            'reference_type' => $this->getMorphClass(),
            'reference_id'   => $this->getKey()
        ]);

        $activity_statuses = $this->mustArray($activity_status);
        foreach ($activity_statuses as $activity_status) {
            $activity->activityStatus()->firstOrCreate([
                'activity_id'    => $activity->getKey(),
                'status'         => $activity_status
            ]);
        }
    }

    public function sortActivity()
    {
        $prop_activity = $this->prop_activity ?? null;
        if (isset($prop_activity)) {
            $flattened = [];
            foreach ($prop_activity as $category => $activities) {
                foreach ($activities as $key => $activity) {
                    $activity["category"] = $category;
                    $activity["key"] = $key;
                    $flattened[] = $activity;
                }
            }

            usort($flattened, function ($a, $b) {
                return strtotime($a["at"]) - strtotime($b["at"]);
            });

            $sorted_prop_activity = [];
            foreach ($flattened as $activity) {
                $sorted_prop_activity[$activity["category"]][$activity["key"]] = [
                    "at" => $activity["at"],
                    "status" => $activity["status"],
                    "message" => $activity["message"]
                ];
            }
            return $sorted_prop_activity;
        }
        return null;
    }
}
