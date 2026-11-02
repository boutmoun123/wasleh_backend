<?php
namespace App\Services;
use App\Models\JobPost;
use App\Models\JobSeekerProfile;

class MatchEngine
{
    public function evaluate(JobSeekerProfile $profile, JobPost $job): array
    {
        $wanted = array_values(array_unique(array_filter(array_map('strval',$job->skills_required ?? []))));
        $got = array_values(array_filter(array_map('strval',$profile->skills ?? [])));
        $normalize = fn (string $s) => mb_strtolower(trim(preg_replace('/\s+/u',' ',$s)), 'UTF-8');
        $gotMap = array_fill_keys(array_map($normalize, $got), true);
        $matched = []; $gaps = [];
        foreach ($wanted as $skill) {
            if (isset($gotMap[$normalize($skill)])) { $matched[]=$skill; }
            else { $gaps[]=$skill; }
        }
        $minYears = (int) $job->min_experience_years;
        $experience = 0;
        foreach ($profile->experience ?? [] as $entry) {
            if (is_array($entry)) { $experience += max(0, (float) ($entry['years'] ?? 0)); }
        }
        $city = (string) ($profile->basic_info['city'] ?? '');
        $jobCity = (string) ($job->city ?? '');
        $weights = []; $points = [];
        if (count($wanted)) { $weights['skills']=70; $points['skills']=count($matched)/count($wanted); }
        if ($minYears) { $weights['experience']=20; $points['experience']=min(1,$experience/$minYears); }
        if ($jobCity !== '') { $weights['city']=10; $points['city']=$city !== '' && $normalize($city)===$normalize($jobCity) ? 1 : 0; }
        $sum = array_sum($weights);
        $score = $sum > 0 ? round(100*array_sum(array_map(fn($k)=>$weights[$k]*$points[$k],array_keys($weights)))/$sum,2) : 0;
        return [
            'score'=>$score,'matched_skills'=>$matched,'missing_skills'=>$gaps,
            'experience'=>['candidate_years'=>$experience,'required_years'=>$minYears,'meets_requirement'=>$experience >= $minYears],
            'city'=>['candidate'=>$city,'required'=>$jobCity,'matches'=>$jobCity==='' || ($city!=='' && $normalize($city)===$normalize($jobCity))],
            'method'=>'rule_based_v1', 'disclaimer'=>'درجة تقريبية وليست ضماناً للتوظيف.',
        ];
    }
}
