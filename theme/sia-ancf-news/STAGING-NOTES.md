# SIA ANCF News 0.4.9-stage.7 — परीक्षण उम्मीदवार

यह केवल staging candidate है; live पर install करने या किसी plugin को हटाने के लिए तैयार नहीं है। साथ की परीक्षण रिपोर्ट और migration limitations पढ़ें।

इसमें सीमित native SEO metadata/schema, Rank Math से कुछ metadata पढ़ने का rehearsal, exact same-host redirects, सीमित विज्ञापन विकल्प, TOC और news/video sitemap हैं। SEO plugin सक्रिय और सही configure होने पर fallback बंद रहता है। SEO तथा redirects का data portability, external analytics/indexing services और अन्य plugins की पूर्ण बराबरी उपलब्ध नहीं है। Video schema content fingerprint और embed ID से बँधा है, फिर भी तीसरे पक्ष की भविष्य की availability की गारंटी नहीं है।

WordPress 7.1.4 / PHP 8.3 पर localhost staging में जाँचा गया। Real AdSense, live cache और Cloudflare के साथ performance या सुरक्षा की गारंटी नहीं। यह candidate, importer या plugin removal production पर चलाने से पहले backup/restore, पूरी migration, compatibility, indexing और security की अलग पुष्टि चाहिए।

URL history के लिए native fallback मौजूदा SIA URL Memory table को reuse करता है। Active plugin होने पर fallback निष्क्रिय रहता है। Table theme activation पर बनता है; यह staged परीक्षण है, पूर्ण migration या production release का प्रमाण नहीं।

इस संस्करण में legacy avatar URL fallback, सीमित copy-to-draft, SIA Entity Author profile fields और unavailable video के schema exclusion का staging परीक्षण शामिल है। पूरे plugin suite की बराबरी और migration अभी पूरा नहीं है।

इस संस्करण में अपने SIA Semantic, Rank Smart, ANCF Core editorial modules के theme-owned fallbacks और स्थानीय read-only analytics summary शामिल हैं। मूल plugins सक्रिय हों तो उनके native fallbacks नहीं चलते। परीक्षण सीमित हैं; सभी plugins का पूर्ण विकल्प या production release तैयार होने का दावा नहीं है।

सीमित GA4 tag/analytics consent, opt-in WebSub main/author/category/tag feed publisher, opt-in IndexNow update/removal publisher, BlogPosting/FAQ/NewsArticle/VideoObject migration और JPEG/PNG से WebP/AVIF alternates का परीक्षण केवल staging में हुआ है। FAQ schema केवल page पर मौजूद visible text से बनता है; Google FAQ rich results बंद कर चुका है।

Actual Rank Math/PRO और Site Kit plugins के coexistence guards staging में जाँचे गए। Site Kit connection test disconnected रहा और कोई OAuth credential या live token copy नहीं किया गया। वास्तविक GA4/Search Console data connection के लिए अलग test/read-only Google authorization चाहिए। दो lyrics records का NewsArticle migration और एक FAQ record संपादकीय mismatch के कारण रोका गया; पुराने ऐतिहासिक LiveBlogPosting को दोबारा live नहीं दिखाया गया।

AVIF/WebP generation original files रखती है, animated PNG को convert नहीं करती और केवल checksum-matched theme-owned alternates हटाती है। IndexNow/WebSub tests mock transport पर हुए; कोई असली hub या indexing request नहीं भेजी गई। ये Google Site Kit, Rank Math PRO या संबंधित plugins की पूर्ण बराबरी नहीं हैं और candidate अभी live deployment/plugin removal के लिए तैयार नहीं है।
