<?php

namespace Tests\Unit;

use App\Services\AtsCvAnalyzer;
use PHPUnit\Framework\TestCase;

class AtsCvAnalyzerTest extends TestCase
{
    public function test_complete_resume_receives_excellent_ats_grade(): void
    {
        $text = implode("\n", [
            'Professional Summary',
            'Skills PHP Laravel MySQL',
            'Work Experience',
            str_repeat('Developed Laravel applications for 20 users and improved performance by 30 percent. ', 25),
            'Education Computer Science',
            'Languages Arabic English',
        ]);

        $result = (new AtsCvAnalyzer)->analyzeText(
            $text,
            'candidate@example.com',
            '+966500000000',
            ['PHP', 'Laravel', 'MySQL'],
        );

        $this->assertSame('excellent', $result['grade']);
        $this->assertGreaterThanOrEqual(85, $result['score']);
        $this->assertTrue($result['checks']['has_quantified_achievements']);
        $this->assertSame('rule_based_ats_v1', $result['method']);
    }

    public function test_incomplete_resume_returns_actionable_recommendations(): void
    {
        $result = (new AtsCvAnalyzer)->analyzeText('Junior developer');

        $this->assertSame('poor', $result['grade']);
        $this->assertFalse($result['checks']['has_email']);
        $this->assertFalse($result['detected_sections']['experience']);
        $this->assertNotEmpty($result['recommendations']);
    }
}
