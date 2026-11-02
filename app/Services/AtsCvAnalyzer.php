<?php

namespace App\Services;

use App\Models\JobSeekerProfile;
use App\Models\User;

class AtsCvAnalyzer
{
    /** @param array<int, string> $skills
     * @return array<string, mixed>
     */
    public function analyzeText(string $text, ?string $email = null, ?string $phone = null, array $skills = []): array
    {
        $normalizedText = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $wordCount = $normalizedText === '' ? 0 : count(preg_split('/\s+/u', $normalizedText) ?: []);
        $sections = $this->detectSections($text);
        $hasEmail = $email !== null || preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/u', $text) === 1;
        $hasPhone = $phone !== null || preg_match('/(?<!\d)(?:\+?\d[\d\s\-()]{7,}\d)(?!\d)/u', $text) === 1;
        $hasQuantifiedAchievements = preg_match('/\b\d+(?:[.,]\d+)?\s*(?:%|percent|projects?|clients?|users?|years?|سنوات|مشاريع|عملاء|مستخدمين)\b/iu', $text) === 1;
        $hasActionLanguage = preg_match('/\b(?:built|developed|managed|improved|increased|reduced|led|created|implemented|طورت|أنشأت|أدرت|حسنت|قدت|نفذت)\b/iu', $text) === 1;

        $breakdown = [
            'contact_information' => ($hasEmail ? 8 : 0) + ($hasPhone ? 7 : 0),
            'standard_sections' => $this->sectionScore($sections),
            'content_quality' => (count($skills) >= 3 ? 10 : count($skills) * 2)
                + ($wordCount >= 200 && $wordCount <= 1200 ? 8 : ($wordCount >= 100 ? 4 : 0))
                + ($hasQuantifiedAchievements ? 7 : 0)
                + ($hasActionLanguage ? 5 : 0),
            'machine_readability' => ($normalizedText !== '' ? 5 : 0)
                + ($wordCount <= 1500 ? 5 : 0)
                + (count(array_filter($sections)) >= 3 ? 5 : 0)
                + (mb_strlen($normalizedText) <= 30000 ? 5 : 0),
        ];
        $score = min(100, array_sum($breakdown));

        return [
            'score' => $score,
            'grade' => match (true) {
                $score >= 85 => 'excellent',
                $score >= 70 => 'good',
                $score >= 50 => 'needs_improvement',
                default => 'poor',
            },
            'method' => 'rule_based_ats_v1',
            'breakdown' => $breakdown,
            'detected_sections' => $sections,
            'detected_skills' => array_values(array_unique($skills)),
            'word_count' => $wordCount,
            'checks' => [
                'has_email' => $hasEmail,
                'has_phone' => $hasPhone,
                'has_quantified_achievements' => $hasQuantifiedAchievements,
                'has_action_language' => $hasActionLanguage,
            ],
            'recommendations' => $this->recommendations($sections, $hasEmail, $hasPhone, $skills, $wordCount, $hasQuantifiedAchievements, $hasActionLanguage),
            'disclaimer' => 'هذه نتيجة إرشادية مبنية على قواعد عامة، وقد تختلف عن نتيجة أنظمة ATS التجارية.',
        ];
    }

    /** @return array<string, mixed> */
    public function analyzeProfile(User $user, JobSeekerProfile $profile): array
    {
        $basic = $profile->basic_info ?? [];
        $parts = [$user->name, $user->email, $user->phone, $basic['job_title_en'] ?? $basic['job_title_ar'] ?? null];
        $summary = $basic['summary_en'] ?? $basic['summary_ar'] ?? null;
        if ($summary) {
            array_push($parts, 'Professional Summary', $summary);
        }
        if ($profile->skills) {
            array_push($parts, 'Skills', implode(' ', $profile->skills));
        }
        if ($profile->experience) {
            array_push($parts, 'Work Experience', $this->flattenEntries($profile->experience));
        }
        if ($profile->education) {
            array_push($parts, 'Education', $this->flattenEntries($profile->education));
        }
        if ($profile->languages) {
            array_push($parts, 'Languages', implode(' ', $profile->languages));
        }

        return $this->analyzeText(
            implode("\n", array_filter($parts, fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')),
            $user->email,
            $user->phone,
            $profile->skills ?? [],
        );
    }

    /** @return array<string, bool> */
    private function detectSections(string $text): array
    {
        $patterns = [
            'summary' => '/\b(?:professional\s+summary|summary|profile|objective)\b|(?:الملخص المهني|نبذة شخصية|الهدف الوظيفي)/iu',
            'skills' => '/\b(?:skills|technical skills|core competencies)\b|(?:المهارات|الكفاءات)/iu',
            'experience' => '/\b(?:work experience|professional experience|employment|experience)\b|(?:الخبرات العملية|الخبرة المهنية|الخبرة)/iu',
            'education' => '/\b(?:education|academic background|qualifications)\b|(?:التعليم|المؤهلات العلمية|الدراسة)/iu',
            'languages' => '/\b(?:languages|language skills)\b|(?:اللغات)/iu',
        ];

        return array_map(fn (string $pattern): bool => preg_match($pattern, $text) === 1, $patterns);
    }

    /** @param array<string, bool> $sections */
    private function sectionScore(array $sections): int
    {
        $weights = ['summary' => 5, 'skills' => 8, 'experience' => 10, 'education' => 7, 'languages' => 5];

        return array_sum(array_map(fn (string $section): int => $sections[$section] ? $weights[$section] : 0, array_keys($weights)));
    }

    /** @param array<string, bool> $sections
     * @param  array<int, string>  $skills
     * @return array<int, string>
     */
    private function recommendations(array $sections, bool $hasEmail, bool $hasPhone, array $skills, int $wordCount, bool $hasQuantifiedAchievements, bool $hasActionLanguage): array
    {
        $recommendations = [];
        if (! $hasEmail || ! $hasPhone) {
            $recommendations[] = 'أضف بريدًا إلكترونيًا ورقم هاتف واضحين في أعلى السيرة الذاتية.';
        }
        foreach ($sections as $section => $present) {
            if (! $present) {
                $recommendations[] = 'أضف قسمًا واضحًا بعنوان '.$section.'.';
            }
        }
        if (count($skills) < 3) {
            $recommendations[] = 'أضف مهارات مرتبطة بالوظيفة باستخدام كلمات مفتاحية دقيقة.';
        }
        if ($wordCount < 200) {
            $recommendations[] = 'أضف تفاصيل مهنية كافية؛ السيرة الحالية مختصرة جدًا.';
        } elseif ($wordCount > 1200) {
            $recommendations[] = 'اختصر السيرة الذاتية وركز على الخبرات والإنجازات الأكثر صلة.';
        }
        if (! $hasQuantifiedAchievements) {
            $recommendations[] = 'استخدم أرقامًا ونسبًا لشرح أثر إنجازاتك.';
        }
        if (! $hasActionLanguage) {
            $recommendations[] = 'ابدأ نقاط الخبرة بأفعال قوية مثل طورت، أدرت، حسنت، أو نفذت.';
        }

        return $recommendations;
    }

    /** @param array<int, mixed> $entries */
    private function flattenEntries(array $entries): string
    {
        $values = [];
        array_walk_recursive($entries, function (mixed $value) use (&$values): void {
            if (is_scalar($value)) {
                $values[] = (string) $value;
            }
        });

        return implode(' ', $values);
    }
}
