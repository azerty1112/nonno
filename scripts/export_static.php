<?php
// standalone exporter for informational pages; avoids loading vendor composer.
function e($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function detectPreferredLanguage() { return 'en'; }
function getSiteBaseUrl() { return 'https://example.com'; }

function getStaticPages($language = null) {
    if ($language === null) {
        $language = detectPreferredLanguage();
    }
    $isArabic = $language === 'ar';
    return [
        'about' => [
            'title' => $isArabic ? 'من نحن' : 'About Us',
            'description' => $isArabic
                ? 'تعرف على رسالتنا التحريرية في مجال السيارات ومعايير النشر التي نعتمدها.'
                : 'Learn more about our automotive editorial mission, publishing standards, and audience-first approach.',
            'content' => $isArabic
                ? [
                    'ننشر محتوى عمليًا عن السيارات يركز على أسئلة المالك الحقيقية مثل الصيانة والشراء والسلامة.',
                    'نجمع بين الأتمتة والمراجعة البشرية لضمان جودة المحتوى وسهولة القراءة.',
                    'نلتزم بالوضوح والشفافية لمساعدة القرّاء على اتخاذ قرارات أفضل.',
                ]
                : [
                    'We publish practical automotive content focused on real ownership questions: maintenance, buying, safety, and long-term value.',
                    'Our editorial workflow combines automated research with final quality checks for readability, originality, and user usefulness.',
                    'We prioritize clear language, transparent labeling, and content that helps readers make better car decisions.',
                ],
        ],
        'contact' => [
            'title' => $isArabic ? 'تواصل معنا' : 'Contact Us',
            'description' => $isArabic
                ? 'هل تحتاج دعمًا أو ترغب في التعاون؟ تواصل مع فريق التحرير والدعم.'
                : 'Need support, want to report an issue, or discuss collaboration? Reach our editorial and support team.',
            'content' => $isArabic
                ? [
                    'لدعم الموقع وطلبات التحرير: contact@example.com',
                    'للشراكات والإعلانات: partnerships@example.com',
                    'نراجع جميع الرسائل ونحاول الرد خلال يومي عمل.',
                ]
                : [
                    'For support and editorial requests, please email: contact@example.com',
                    'For ad and business inquiries, please email: partnerships@example.com',
                    'We review all messages and aim to reply within 2 business days.',
                ],
        ],
        'privacy' => [
            'title' => $isArabic ? 'سياسة الخصوصية' : 'Privacy Policy',
            'description' => $isArabic
                ? 'اعرف كيف نجمع ونستخدم ونحمي بيانات الزوار وملفات تعريف الارتباط.'
                : 'Read how we collect, use, and protect visitor data, cookies, and analytics information.',
            'content' => $isArabic
                ? [
                    'قد نستخدم التحليلات وتقنيات الإعلانات (مثل الكوكيز) لفهم الزيارات وتحسين التجربة.',
                    'لا نجمع عمدًا بيانات شخصية حساسة عبر الصفحات العامة، ونستخدم بيانات التواصل للرد فقط.',
                    'قد تعالج خدمات الطرف الثالث البيانات وفق سياساتها الخاصة، ويمكنك تعطيل الكوكيز من إعدادات المتصفح.',
                ]
                : [
                    'We may use analytics and advertising technologies (such as cookies and measurement scripts) to understand traffic and improve user experience.',
                    'We do not intentionally collect sensitive personal information through public pages. If you contact us directly, we only use your information to respond.',
                    'Third-party services (including ad providers) may process data according to their own privacy policies. You can disable cookies from your browser settings.',
                ],
        ],
        'terms' => [
            'title' => $isArabic ? 'الشروط والأحكام' : 'Terms of Use',
            'description' => $isArabic
                ? 'شروط الاستخدام التي تغطي الاستخدام المقبول وحقوق الملكية والتنبيهات القانونية.'
                : 'Website terms covering acceptable use, intellectual property, disclaimers, and content usage.',
            'content' => $isArabic
                ? [
                    'باستخدامك للموقع فإنك توافق على استخدام المحتوى لأغراض قانونية ومعلوماتية شخصية فقط.',
                    'جميع المقالات لأغراض معرفية عامة ولا تُعد بديلاً عن الاستشارات المهنية.',
                    'قد نقوم بتحديث المحتوى والسياسات في أي وقت لضمان الجودة والامتثال.',
                ]
                : [
                    'By using this website, you agree to use the content for lawful and personal informational purposes only.',
                    'All articles are provided for general information and do not replace professional legal, financial, or mechanical advice.',
                    'We may update content and site policies at any time to maintain quality, compliance, and platform requirements.',
                ],
        ],
    ];
}

function exportStaticPages($language = null) {
    $pages = getStaticPages($language);
    $baseUrl = getSiteBaseUrl();
    if ($baseUrl === '') {
        $baseUrl = 'http://localhost';
    }
    foreach ($pages as $key => $info) {
        $html = '<!doctype html>\n<html lang="' . e($language ?: detectPreferredLanguage()) . '">\n<head>\n';
        $html .= '  <meta charset="UTF-8">\n';
        $html .= '  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n';
        $html .= '  <title>' . e($info['title']) . '</title>\n';
        if (!empty($info['description'])) {
            $html .= '  <meta name="description" content="' . e($info['description']) . '">\n';
        }
        $html .= '  <link rel="canonical" href="' . e(rtrim($baseUrl, '/') . '/index.php?doc=' . rawurlencode($key)) . '">\n';
        $html .= '</head>\n<body>\n';
        $html .= '<main>\n<h1>' . e($info['title']) . '</h1>\n';
        foreach ((array)$info['content'] as $para) {
            $html .= '<p>' . e($para) . '</p>\n';
        }
        $html .= '</main>\n</body>\n</html>\n';
        $dest = __DIR__ . '/../' . $key . '.html';
        file_put_contents($dest, $html);
    }
}

exportStaticPages();
echo "static pages exported\n";
