<?php
/**
 * Plugin Name: DeviantArt Timeline
 * Description: Displays imported DeviantArt gallery images in a Google Photos-style timeline. Usage: [deviantart_timeline] or [deviantart_landing]
 * Version: 4.45
 * Author: Antigravity
 */

if (!defined('ABSPATH')) {
    exit;
}

// FORCE DARK BROWSER CHROME ON MOBILE
function da_timeline_header_meta()
{
    echo '<meta name="theme-color" content="#050505" />' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />' . "\n";
}
add_action('wp_head', 'da_timeline_header_meta');

/* --- SHARED STYLES --- */
function da_print_styles()
{
    ?>
    <style>
        /* GLASSMORPHISM & DARK MODE CSS */
        :root {
            --da-bg: transparent;
            --da-text: #eee;
            --da-meta: #aaa;
            --da-gap: 12px;
            /* Fixed gap */
            --da-row-height: 320px;
            --da-scrubber-width: 70px;
        }

        /* Essential Overrides v4.12 - Fixed Mobile Blue */
        body,
        #page,
        .site-content,
        .entry-content,
        .site-main {
            background-color: #050505 !important;
            color: #ccc !important;
        }

        .site-header,
        #masthead {
            background-color: #050505;
        }

        /* Shared Canvas/Wrapper styles */
        .da-ambient-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
            overflow: hidden;
            background: #000;
        }
    
        
        
        
        
        
        /* GLITCH CONSOLE STYLES (v6 Unfurl) */
        /* Ensure the wrapper doesn't clip the text spawning below */
        .da-landing-wrapper {
            overflow: visible !important;
        }

        .da-choice-box {
            position: relative;
            z-index: 10; 
            overflow: visible !important; /* CRITICAL */
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Container for the glitch text */
        .da-glitch-container {
            position: absolute;
            top: 60px; /* Hard push below the button (height is usually ~40-50px) */
            left: 50%;
            transform: translateX(-50%);
            width: 300px; 
            text-align: center;
            pointer-events: none;
            z-index: 20; /* On top of everything */
        }

        .da-glitch-text {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: rgba(255,255,255,0.6);
            white-space: pre-wrap; /* Wrap text */
            word-break: break-word;
            line-height: 1.5;
            text-shadow: 0 0 5px rgba(0,0,0,1.0); /* Strong shadow for readability */
            
            /* Blur effect */
            filter: blur(0.5px);
            transition: filter 0.5s;
        }
        
        .da-glitch-text.theme-ai { color: #aaffdd; }
        .da-glitch-text.theme-human { color: #ffccaa; }
</style>
    <?php
}

/* --- MAIN TIMELINE SHORTCODE --- */
function da_timeline_shortcode($atts)
{
    ob_start();
    da_print_styles();

    $args = ['post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC'];
    $query = new WP_Query($args);
    $timeline = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $file = get_attached_file($id);

            // FILTER: Only show images with 'deviantart_' in the filename
            if (!$file || strpos(basename($file), 'deviantart_') === false)
                continue;

            $year = get_the_date('Y');
            $month = get_the_date('F');
            $month_sort = get_the_date('m');
            $img_meta = wp_get_attachment_metadata($id);

            // Calc Aspect Ratio exactly
            $w = isset($img_meta['width']) ? $img_meta['width'] : 800;
            $h = isset($img_meta['height']) ? $img_meta['height'] : 600;
            $aspect = $h > 0 ? $w / $h : 1.33;

            if (!isset($timeline[$year]))
                $timeline[$year] = [];
            if (!isset($timeline[$year][$month_sort . '_' . $month]))
                $timeline[$year][$month_sort . '_' . $month] = [];

            $timeline[$year][$month_sort . '_' . $month][] = [
                'id' => $id,
                'title' => get_the_title(),
                'src' => wp_get_attachment_image_url($id, 'large'),
                'full' => wp_get_attachment_image_url($id, 'full'),
                'aspect' => $aspect,
                'date' => get_the_date('F j, Y'),
                'desc' => get_the_excerpt(),
            ];
        }
        wp_reset_postdata();
    }
    krsort($timeline);

    if (empty($timeline)) {
        echo "<h1 style='color:red; z-index:9999; position:relative;'>DEBUG: No images found in WP_Query. Count: " . $query->found_posts . "</h1>";
    }
    ?>
    <style>
        .da-timeline-wrapper {
            background: var(--da-bg);
            color: var(--da-text);
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
            padding: 20px 80px 40px 20px;
            margin: -20px -50px;
            box-sizing: border-box;
            position: relative;
        }

        /* STICKY HEADERS */
        .da-sticky-header {
            position: sticky;
            top: 32px;
            z-index: 20;
            background: rgba(5, 5, 5, 0.5);
            /* More transparent glass */
            padding: 15px 20px;
            margin-bottom: 25px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            display: flex;
            align-items: baseline;
            gap: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            margin-right: 60px;
            /* Make room for scrubber */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .da-year-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            margin: 0;
            letter-spacing: -0.5px;
        }

        .da-month-title {
            font-size: 1.2rem;
            color: var(--da-meta);
            margin: 0;
            font-weight: 400;
        }

        /* GRID */
        .da-month-grid {
            display: flex;
            flex-wrap: wrap;
            gap: var(--da-gap);
            margin-bottom: 60px;
        }

        .da-item {
            flex-grow: 1;
            height: var(--da-row-height);
            position: relative;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);

            /* SCROLL ANIMATION INITIAL STATE */
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.6s ease-out, transform 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        .da-item.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Animation Trigger */

        .da-item img {
            height: 100%;
            min-width: 100%;
            object-fit: cover;
            vertical-align: bottom;
            transition: opacity 0.3s, transform 0.7s;
        }

        .da-item:hover {
            transform: translateY(-4px) scale(1.005);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
            border-color: rgba(255, 255, 255, 0.4);
            z-index: 2;
        }

        .da-item:hover img {
            transform: scale(1.05);
            filter: contrast(1.1);
        }

        .da-month-grid::after {
            content: '';
            flex-grow: 999999999;
        }

        /* AMBIENT BACKGROUND */
        .da-timeline-wrapper .da-bg-group {
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 3s;
            z-index: 0;
        }

        .da-timeline-wrapper .da-bg-group.active {
            opacity: 1;
            z-index: 1;
        }

        .da-timeline-wrapper .da-fog-layer {
            position: absolute;
            inset: -20%;
            animation: da-fog-roll 45s infinite linear;
            display: flex;
        }

        .da-timeline-wrapper .da-fog-half {
            flex: 1;
            height: 100%;
            background-image: var(--da-current-img);
            background-size: cover;
            background-position: center;
            filter: blur(50px) brightness(0.5) contrast(1.2) saturate(1.2);
            opacity: 0.8;
        }

        .da-timeline-wrapper .da-fog-half.right {
            transform: scaleX(-1);
        }

        @keyframes da-fog-roll {
            0% {
                transform: scale(1);
                filter: hue-rotate(0deg);
            }

            50% {
                transform: scale(1.1);
                filter: hue-rotate(10deg);
            }

            100% {
                transform: scale(1);
                filter: hue-rotate(0deg);
            }
        }

        /* SIDEBAR SCRUBBER (Enhanced) */
        .da-scrubber {
            position: fixed;
            right: 20px;
            top: 15%;
            bottom: 15%;
            width: 50px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
            gap: 8px;
            z-index: 100;
            pointer-events: auto;
            /* Allow clicking */
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            /* Connection Line */
            padding-right: 10px;
        }

        .da-scrubber-year {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            margin-bottom: 12px;
            position: relative;
        }

        .da-scrubber-label {
            font-size: 0.8rem;
            color: var(--da-meta);
            font-weight: 700;
            margin-bottom: 4px;
            opacity: 0.5;
            transition: opacity 0.3s;
        }

        .da-scrubber-year:hover .da-scrubber-label,
        .da-scrubber-year.active .da-scrubber-label {
            opacity: 1;
            color: #fff;
        }

        .da-scrubber-dot {
            width: 8px;
            height: 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            cursor: pointer;
        }

        /* Hover/Active State */
        .da-scrubber-dot:hover,
        .da-scrubber-dot.active {
            width: 14px;
            height: 14px;
            background: #fff;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.8);
        }

        /* Tooltip Label */
        .da-scrubber-dot::after {
            content: attr(title);
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%) translateX(10px);
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: all 0.2s;
        }

        .da-scrubber-dot:hover::after,
        .da-scrubber-dot.active::after {
            opacity: 1;
            transform: translateY(-50%) translateX(0);
        }

        /* GLOBAL VIGNETTE (Cheap, separate layer) */
        .da-vignette {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 6;
            background: radial-gradient(circle at center, transparent 20%, #000 120%);
        }

        /* 4-PANEL BLUR FRAMES (v4.37 High Vis) */
        .da-blur-panel {
            position: fixed;
            z-index: 5;
            pointer-events: none;
            /* Stronger Blur & Tint */
            backdrop-filter: blur(12px) brightness(0.7);
            -webkit-backdrop-filter: blur(12px) brightness(0.7);
            transition: all 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
            background: rgba(0, 0, 0, 0.6);
            /* High opacity fallback */
        }

        /* Initial State: Top panel covers everything */
        #da-panel-n {
            top: 0;
            left: 0;
            width: 100vw;
            height: 100dvh;
        }

        #da-panel-s {
            bottom: 0;
            left: 0;
            width: 100vw;
            height: 0;
        }

        #da-panel-w {
            top: 0;
            left: 0;
            width: 0;
            height: 0;
        }

        #da-panel-e {
            top: 0;
            right: 0;
            width: 0;
            height: 0;
        }

        /* LIGHTBOX */
        .da-lightbox {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: rgba(0, 0, 0, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
            backdrop-filter: blur(20px);
        }

        .da-lightbox.open {
            opacity: 1;
            pointer-events: auto;
        }

        .da-lightbox img {
            max-width: 90vw;
            max-height: 90vh;
            border-radius: 4px;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.8);
            transform: scale(0.95);
            transition: transform 0.3s;
        }

        .da-lightbox.open img {
            transform: scale(1);
        }

        .da-lb-close {
            position: absolute;
            top: 30px;
            right: 40px;
            color: #fff;
            font-size: 3rem;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .da-lb-close:hover {
            opacity: 1;
        }
    </style>

    <div class="da-timeline-wrapper">
        <!-- RENDER ITEMS -->
        <?php foreach ($timeline as $year => $months):
            // Sticky Header for Year/Month provided by first item of month group
            foreach ($months as $month_key => $items):
                $parts = explode('_', $month_key);
                $nice_month = $parts[1];
                $section_id = "group-$year-$month_key";
                ?>
                <div id="<?php echo $section_id; ?>" class="da-sticky-header" data-year="<?php echo $year; ?>"
                    data-month="<?php echo $nice_month; ?>">
                    <h2 class="da-year-title"><?php echo $year; ?></h2>
                    <h3 class="da-month-title"><?php echo $nice_month; ?></h3>
                </div>
                <div class="da-month-grid">
                    <?php foreach ($items as $item):
                        $flex = 320 * $item['aspect'];
                        ?>
                        <div class='da-item' style='flex-grow:<?php echo $item['aspect']; ?>; flex-basis:<?php echo $flex; ?>px'
                            data-src='<?php echo $item['full']; ?>' data-thumb='<?php echo $item['src']; ?>'>
                            <img src='<?php echo $item['src']; ?>' loading='lazy' alt='<?php echo esc_attr($item['title']); ?>'>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach;
        endforeach; ?>

        <!-- BACKGROUND FOG SYSTEM -->
        <div class="da-ambient-wrapper">
            <div id="da-bg-a" class="da-bg-group active">
                <div class="da-fog-layer">
                    <div class="da-fog-half"></div>
                    <div class="da-fog-half right"></div>
                </div>
            </div>
            <div id="da-bg-b" class="da-bg-group">
                <div class="da-fog-layer">
                    <div class="da-fog-half"></div>
                    <div class="da-fog-half right"></div>
                </div>
            </div>
        </div>

        <!-- SIDEBAR SCRUBBER (Restored) -->
        <div class="da-scrubber">
            <?php foreach ($timeline as $year => $months): ?>
                <div class="da-scrubber-year" data-target="<?php echo $year; ?>">
                    <span class="da-scrubber-label"><?php echo $year; ?></span>
                    <?php foreach ($months as $month_key => $items):
                        $parts = explode('_', $month_key);
                        $nice_month = $parts[1];
                        $section_id = "group-$year-$month_key";
                        ?>
                        <a href="#<?php echo $section_id; ?>" class="da-scrubber-dot" title="<?php echo "$nice_month $year"; ?>"
                            data-section="<?php echo $section_id; ?>">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- LIGHTBOX HTML -->
    <div class="da-lightbox" id="da-lightbox">
        <div class="da-lb-close">&times;</div>
        <img id="da-lb-img" src="">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // 1. LIGHTBOX
            const lb = document.getElementById('da-lightbox');
            const lbImg = document.getElementById('da-lb-img');
            const items = document.querySelectorAll('.da-item');

            items.forEach(item => {
                item.addEventListener('click', () => {
                    lbImg.src = item.dataset.src;
                    lb.classList.add('open');
                });
            });
            lb.addEventListener('click', (e) => {
                if (e.target !== lbImg) lb.classList.remove('open');
            });

            // 2. SCROLL ANIMATIONS (Fade In Up)
            const itemObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        // Optional: Unobserve after animating? 
                        // itemObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            items.forEach(item => itemObserver.observe(item));

            // 3. BACKGROUND CHANGING (Fog)
            const bgA = document.getElementById('da-bg-a').querySelector('.da-fog-layer');
            const bgB = document.getElementById('da-bg-b').querySelector('.da-fog-layer');
            let activeGroup = 'a';

            const updateBg = (src) => {
                const targetLayer = activeGroup === 'a' ? bgB : bgA;
                const targetGroup = activeGroup === 'a' ? document.getElementById('da-bg-b') : document.getElementById('da-bg-a');
                const currentGroup = activeGroup === 'a' ? document.getElementById('da-bg-a') : document.getElementById('da-bg-b');

                targetLayer.querySelectorAll('.da-fog-half').forEach(el => el.style.setProperty('--da-current-img', `url(${src})`));
                targetGroup.classList.add('active');
                currentGroup.classList.remove('active');

                activeGroup = activeGroup === 'a' ? 'b' : 'a';
            };

            const bgObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && Math.random() > 0.8) {
                        updateBg(entry.target.dataset.thumb);
                    }
                });
            }, { threshold: 0.2 });
            items.forEach(item => bgObserver.observe(item));

            // 4. SCRUBBER HIGHLIGHTING
            const headers = document.querySelectorAll('.da-sticky-header');
            const dots = document.querySelectorAll('.da-scrubber-dot');
            const yearLabels = document.querySelectorAll('.da-scrubber-year');

            const scrubObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        // Activate Dot
                        dots.forEach(d => d.classList.remove('active'));
                        const id = entry.target.id;
                        const validDot = document.querySelector(`.da-scrubber-dot[data-section="${id}"]`);
                        if (validDot) {
                            validDot.classList.add('active');
                            // Activate Year Group (Parent)
                            yearLabels.forEach(y => y.classList.remove('active'));
                            validDot.parentElement.classList.add('active');
                        }
                    }
                });
            }, { rootMargin: '-10% 0px -60% 0px' }); // Highlights when header is in top 40% of screen

            headers.forEach(h => scrubObserver.observe(h));
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('deviantart_timeline', 'da_timeline_shortcode');


/* --- LANDING PAGE SHORTCODE (STABLE INK v4.21 - Updated v4.45 Safe Mode) --- */
function da_landing_shortcode($atts)
{
    ob_start();
    ?>
    <style>
        /* LANDING PAGE ISOLATION */
        /* Hide Footer Only */
        footer,
        #colophon,
        .site-footer,
        .elementor-location-footer {
            display: none !important;
        }

        /* Ensure Header is visible and top-most */
        header,
        #masthead,
        .site-header,
        #wpadminbar,
        .elementor-location-header,
        #header {
            /* display: block !important; */
            position: relative;
            z-index: 9999 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        /* Body Transparent to show Canvas (-1), HTML provides Black Base */
        html {
            background: #050505 !important;
            height: 100% !important;
        }

        body {
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            background: transparent !important;
            height: 100% !important;
        }

        /* Ensure content sits ABOVE the glass */
        .da-landing-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 40px;
            z-index: 10;
        }

        /* Canvas at Z-1 (Standard Background) */
        canvas#da-webgl {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
            opacity: 1 !important;
        }

        .da-choice-box {
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            transition: all 0.4s ease;
            cursor: pointer;
            font-family: 'Inter', system-ui, sans-serif;
            position: relative;
            overflow: hidden;
            pointer-events: auto;
        }

        .da-choice-box:hover {
            transform: translateY(-10px) scale(1.05);
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
        }

        .da-choice-box::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: 0.5s;
        }

        .da-choice-box:hover::after {
            left: 100%;
        }

        @media (max-width: 700px) {
            .da-landing-wrapper {
                flex-direction: column;
            }
        }

        /* STYLE SWITCHER KEYFRAMES */
        @keyframes da-fade-in {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* BUTTON GROUP */
        .da-btn-group {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 15px;
            z-index: 100;
        }

        .da-float-btn {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: rgba(255, 255, 255, 0.7);
        }

        .da-float-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
            color: #fff;
        }

        .da-float-btn.active {
            background: rgba(255, 255, 255, 0.3);
            color: #fff;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.3);
        }

        /* GLOBAL VIGNETTE */
        .da-vignette {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background: radial-gradient(circle at center, transparent 20%, #000 120%);
        }

        /* PERMANENT TOOLBAR UI */
        #da-mode-toolbar {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 99999999;
            display: flex;
            gap: 12px;
            padding: 10px;
            background: rgba(10, 10, 10, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            pointer-events: auto;
            /* Force clickable */
        }

        .da-mode-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.6);
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .da-mode-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            transform: translateY(-2px);
        }

        .da-mode-btn.selected {
            background: #fff;
            color: #000;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.4);
        }

        /* GLOBAL GLASS OVERLAY */
        .da-glass-overlay {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            pointer-events: none;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            background: rgba(0, 0, 0, 0.01);
            transition: clip-path 0.4s cubic-bezier(0.2, 0.8, 0.2, 1), -webkit-clip-path 0.4s;
            -webkit-clip-path: inset(0 0 0 0);
            clip-path: inset(0 0 0 0);
        }

        canvas#da-webgl {
            transition: opacity 1s ease;
        }
    </style>

    <!-- HTML STRUCTURE -->
    <div class="da-vignette"></div>
    <div class="da-glass-overlay"></div>

    <!-- UI: PERMANENT TOOLBAR -->
    <div id="da-mode-toolbar">
        <!-- Mode 0: Smoke -->
        <div class="da-mode-btn" data-mode="0" title="Smoke" onclick="window.DA_CTRL.select(0)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 15h18M3 10h18M5 20h14M7 5h10"></path>
            </svg>
        </div>
        <!-- Mode 1: Decay -->
        <div class="da-mode-btn" data-mode="1" title="Decay" onclick="window.DA_CTRL.select(1)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2c-4 0-8 4-8 9 0 3.5 2 6.5 5 8v3h6v-3c3-1.5 5-4.5 5-8 0-5-4-9-8-9z"></path>
                <circle cx="9" cy="10" r="1.5"></circle>
                <circle cx="15" cy="10" r="1.5"></circle>
            </svg>
        </div>
        <!-- Mode 2: Void (Original) -->
        <div class="da-mode-btn" data-mode="2" title="Void" onclick="window.DA_CTRL.select(2)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <circle cx="12" cy="12" r="2"></circle>
            </svg>
        </div>
        <!-- Mode 3: Zdzisław (Rorschach) -->
        <div class="da-mode-btn" data-mode="3" title="Zdzisław" onclick="window.DA_CTRL.select(3)">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 10h.01M15 10h.01M12 2a8 8 0 0 0-8 8v12l3-3 2.5 2.5L12 19l2.5 2.5L17 19l3 3V10a8 8 0 0 0-8-8z"></path>
            </svg>
        </div>
    </div>

    <!-- WRAPPER -->
    <div class="da-landing-wrapper">
        <a href="https://sardistic.com/ai/" class="da-choice-box">Artificial</a>
        <a href="https://sardistic.com/gallery-timeline/" class="da-choice-box">Organic</a>
    </div>

    <canvas id="da-webgl"></canvas>

    <!-- SHADERS -->
    <script id="vs" type="x-shader/x-vertex">
                            attribute vec2 position; varying vec2 vUv;
                            void main() { vUv = position; gl_Position = vec4(position, 0.0, 1.0); }
                        </script>
    <script id="fs" type="x-shader/x-fragment">
                            precision mediump float; uniform float uTime; uniform vec2 uResolution; uniform vec2 uMouse; uniform float uMode;
        
                            // COMMON UTILS
                            mat2 rot(float a) { float s=sin(a), c=cos(a); return mat2(c,-s,s,c); }
                            float random(in vec2 st) { return fract(sin(dot(st.xy,vec2(12.9898,78.233)))*43758.5453123); }
                            float noise(in vec2 st) {
                                vec2 i=floor(st); vec2 f=fract(st);
                                float a=random(i); float b=random(i+vec2(1,0)); float c=random(i+vec2(0,1)); float d=random(i+vec2(1,1));
                                vec2 u=f*f*(3.0-2.0*f);
                                return mix(a,b,u.x)+(c-a)*u.y*(1.0-u.x)+(d-b)*u.x*u.y;
                            }
                            #define OCTAVES 5
                            float fbm(in vec2 st) {
                                float v=0.0; float a=0.5; mat2 m=rot(0.5);
                                for(int i=0; i<OCTAVES; i++){ float n = abs(noise(st)*2.0-1.0); n = 1.0 - n; n = n*n; v+=a*n; st=m*st*2.1; a*=0.45; }
                                return v;
                            }

                            // --- MODE 0: SMOKE (Original) ---
                            vec3 getSmoke(vec2 uv, float t, float w) {
                                vec2 p = uv * 1.6;
                                vec2 q, r;
                                q.x = fbm(p); q.y = fbm(p+vec2(5.2,1.3));
                                r.x = fbm(p+4.0*q*w+vec2(1.7,9.2)+0.15*t);
                                r.y = fbm(p+4.0*q*w+vec2(8.3,2.8)+0.126*t);
                                float f = fbm(p+4.0*r+vec2(0.0,-0.4*t));
                                float ink = f * length(r);
                                ink = pow(ink, 1.5) * 2.5;
            
                                float dist = length(uv);
                                ink *= smoothstep(1.8, 0.2, dist); // Mask
            
                                vec3 col = vec3(ink * 0.95);
                                return mix(vec3(0.02), vec3(0.95), ink);
                            }

                            // --- MODE 1: BEKSINSKI (Decay) ---
                            vec3 getBeksinski(vec2 uv, float t, float w) {
                                vec2 p = uv * 1.2;
                                float slowT = t * 0.25; // Slower
                                vec2 q, r;
                                q.x = fbm(p); q.y = fbm(p+vec2(5.2,1.3));
                                r.x = fbm(p+4.0*q*w+vec2(1.7,9.2)+0.15*slowT);
                                r.y = fbm(p+4.0*q*w+vec2(8.3,2.8)+0.126*slowT);
                                float f = fbm(p+4.0*r+vec2(0.0,-0.4*slowT));
            
                                float ink = f * length(r);
                                ink = pow(ink, 1.2) * 3.5; // High contrast
            
                                float dist = length(uv);
                                ink *= smoothstep(1.8, 0.2, dist);
            
                                // Rust/Bone Palette
                                vec3 c_void = vec3(0.02, 0.0, 0.0);
                                vec3 c_rust = vec3(0.45, 0.15, 0.05);
                                vec3 c_bone = vec3(0.70, 0.65, 0.55);
            
                                vec3 col = mix(c_void, c_rust, smoothstep(0.0, 0.4, ink));
                                col = mix(col, c_bone, smoothstep(0.3, 1.0, ink));
                                return col * smoothstep(1.5, 0.5, dist); // Vignette
                            }

            // Helper: Smooth Min for organic blending
            float smin(float a, float b, float k) {
                float h = clamp(0.5 + 0.5*(b-a)/k, 0.0, 1.0);
                return mix(b, a, h) - k*h*(1.0-h);
            }

            // --- MODE 2: VOID SORT (Original Inward) ---
            vec3 getVoid(vec2 uv, float t) {
                vec2 p = uv; p.x = abs(p.x);
                float flow = t * 0.2;
                float scan = noise(vec2(p.x * 2.0 + flow, p.y * 100.0)); 
                float voidDist = length(p - vec2(0.0, 0.1)) - 0.3; 
                voidDist += noise(p * 5.0 + t*0.1) * 0.1; 
                float pileUp = smoothstep(0.1, 0.0, voidDist); 
                float streams = smoothstep(0.4, 0.6, scan);
                streams *= smoothstep(0.0, 0.2, voidDist); 
                streams *= smoothstep(1.0, 0.0, p.x); 
                float edge = smoothstep(0.05, 0.0, abs(voidDist));
                vec3 col = vec3(0.0); 
                col += vec3(streams) * 0.8; 
                col += vec3(edge) * 0.5; 
                return col;
            }

                                                                                                // --- MODE 3: ZDZISLAW (Mega-Titan v4.64) ---
            float smax(float a, float b, float k) {
                return -smin(-a, -b, k);
            }

            vec3 getZdzislaw(vec2 uv, float t) {
                vec2 p = uv; 
                p.x = abs(p.x); 
                
                float tMorph = t * 0.12; 
                float tFlow  = t * 0.18;  

                // 1. MORPHING STATES
                float morph = 0.5 + 0.5 * sin(tMorph); 

                // SHAPE A: "The Ribbed Hive" (MEGA SCALE)
                float dA = length(p - vec2(0.0, 0.25)) - 0.45;
                for(int i=1; i<=3; i++) {
                    float fi = float(i);
                    vec2 pos = vec2(0.18, -0.15 - 0.28*fi); 
                    float rib = length(p - pos) - (0.24 - 0.03*fi);
                    dA = smin(dA, rib, 0.3); 
                }
                float pores = sin(p.x*12.0)*sin(p.y*12.0);
                dA += pores * 0.03;

                // SHAPE B: "The Faceless" (MEGA SCALE)
                float box = length(max(abs(p - vec2(0.0, -0.15))-vec2(0.25, 0.85),0.0)); 
                float dB = box + min(max(abs(p.x)-0.25, abs(p.y+0.15)-0.85), 0.0); 
                
                float cavity = length(p - vec2(0.0, -0.2)) - 0.4; 
                dB = smax(dB, -cavity, 0.2); 
                
                float shoulders = length(p - vec2(0.55, 0.35)) - 0.35;
                dB = smin(dB, shoulders, 0.3);

                // BLEND
                float d = mix(dA, dB, smoothstep(0.1, 0.9, morph));

                // 2. SURFACE DETAILS
                float boneNoise = fbm(p * 3.5 + tMorph*0.4); 
                d += boneNoise * 0.05; 

                // 3. EMANATING OOZE (Unbound)
                float r = length(uv);
                float a = atan(uv.y, uv.x);
                
                float flowPhase = tFlow - d * 2.0; 
                float strands = noise(vec2(a * 3.0 + p.x, r * 2.0 - flowPhase * 1.5));
                strands = smoothstep(0.4, 0.6, strands);
                
                float emanate = smoothstep(0.2, -0.3, d);
                // MASK EXTENSION: Infinite/Corners
                emanate *= smoothstep(3.0, 0.4, r); 
                
                float oozeVis = strands * emanate;

                // 4. COMPOSITE
                float bodyMask = smoothstep(0.01, -0.01, d);
                float gloom = smoothstep(0.35, 0.65, boneNoise);
                
                vec3 c_bone  = vec3(0.55, 0.53, 0.48);
                vec3 c_void  = vec3(0.02, 0.02, 0.03);
                vec3 c_ooze  = vec3(0.60, 0.50, 0.40); 
                
                vec3 bodyCol = mix(c_bone, c_void, gloom * 0.85);
                bodyCol *= smoothstep(-1.2, 0.8, p.y); 

                vec3 col = vec3(0.0);
                col += c_ooze * oozeVis * 0.65; 
                col = mix(col, bodyCol, bodyMask);
                
                // Fog fills the screen
                float fog = smoothstep(0.7, -0.2, d) * fbm(p*1.2 - tFlow);
                col += vec3(0.2, 0.18, 0.15) * fog * 0.55;

                return col;
            }

            void main() {
                vec2 uv = gl_FragCoord.xy/uResolution.xy; uv = uv*2.0-1.0; uv.x *= uResolution.x/uResolution.y;
                vec2 vUv = uv; vUv.x = abs(vUv.x); vUv.x -= 0.1*(1.0-vUv.y)*0.5;

                float t = uTime * 0.2;
                float mX = uMouse.x/uResolution.x;
                float w = 0.2 + mX*0.5;

                float w0 = max(0.0, 1.0 - abs(uMode - 0.0));
                float w1 = max(0.0, 1.0 - abs(uMode - 1.0));
                float w2 = max(0.0, 1.0 - abs(uMode - 2.0));
                float w3 = max(0.0, 1.0 - abs(uMode - 3.0));

                vec3 col = vec3(0.0);
                if(w0 > 0.01) col += getSmoke(vUv, t, w) * w0;
                if(w1 > 0.01) col += getBeksinski(vUv, uTime, w) * w1;
                if(w2 > 0.01) col += getVoid(uv, uTime) * w2;
                if(w3 > 0.01) col += getZdzislaw(uv, uTime) * w3;

                gl_FragColor = vec4(col, 1.0);
            }
        </script>

    <!-- SAFE MODE JS: UI First, Then WebGL -->
    <script data-cfasync="false">
        // 1. DEFINE CONTROLLER IN GLOBAL SCOPE IMMEDIATELY
        window.DA_CTRL = {
            targetMode: 3.0,
            select: function (mode) {
                this.targetMode = parseFloat(mode);

                // UI Update
                const opts = document.querySelectorAll('.da-mode-btn');
                opts.forEach(o => {
                    if (parseFloat(o.getAttribute('data-mode')) === this.targetMode) o.classList.add('selected');
                    else o.classList.remove('selected');
                });
            }
        };

        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                let currentMode = 3.0;
                let isPaused = false;
                let rendering = true;

                const c = document.getElementById('da-webgl');

                // Init Selection UI
                const opts = document.querySelectorAll('.da-mode-btn');
                opts.forEach(o => {
                    if (parseFloat(o.getAttribute('data-mode')) === window.DA_CTRL.targetMode) o.classList.add('selected');
                });

                // --- 2. WEBGL SETUP ---
                if (!c) return;
                const g = c.getContext('webgl');
                if (!g) return;

                const r = () => { c.width = window.innerWidth; c.height = window.innerHeight; g.viewport(0, 0, c.width, c.height); };
                window.addEventListener('resize', r); r();

                const cs = (g, t, s) => { const h = g.createShader(t); g.shaderSource(h, s); g.compileShader(h); return h; };
                const p = g.createProgram();

                // Safety Compile Checks
                const vsS = document.getElementById('vs').innerText;
                const fsS = document.getElementById('fs').innerText;

                const vSh = cs(g, g.VERTEX_SHADER, vsS);
                const fSh = cs(g, g.FRAGMENT_SHADER, fsS);

                g.attachShader(p, vSh);
                g.attachShader(p, fSh);
                g.linkProgram(p);

                if (!g.getProgramParameter(p, g.LINK_STATUS)) {
                    console.error('Shader Link Error:', g.getProgramInfoLog(p));
                    return;
                }
                g.useProgram(p);

                const b = g.createBuffer(); g.bindBuffer(g.ARRAY_BUFFER, b);
                g.bufferData(g.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]), g.STATIC_DRAW);

                const pl = g.getAttribLocation(p, "position"); g.enableVertexAttribArray(pl); g.vertexAttribPointer(pl, 2, g.FLOAT, false, 0, 0);
                const ut = g.getUniformLocation(p, 'uTime');
                const ur = g.getUniformLocation(p, 'uResolution');
                const um = g.getUniformLocation(p, 'uMouse');
                const uModeLoc = g.getUniformLocation(p, 'uMode');

                let mx = window.innerWidth / 2, my = window.innerHeight / 2;
                window.addEventListener('mousemove', e => { mx = e.clientX; my = window.innerHeight - e.clientY; });

                // Loop function
                function runLoop(t) {
                    if (!rendering) return;

                    // FORCE PROGRAM ACTIVE to prevent INVALID_OPERATION
                    g.useProgram(p);

                    // Sync with Global State
                    let target = window.DA_CTRL ? window.DA_CTRL.targetMode : 2.0;

                    let diff = target - currentMode;
                    if (Math.abs(diff) > 0.01) currentMode += diff * 0.05;
                    else currentMode = target;

                    g.uniform1f(ut, t * 0.001);
                    g.uniform2f(ur, c.width, c.height);
                    g.uniform2f(um, mx, my);
                    g.uniform1f(uModeLoc, currentMode);

                    g.drawArrays(g.TRIANGLES, 0, 6);
                    requestAnimationFrame(runLoop);
                }
                requestAnimationFrame(runLoop);

                // --- 3. CLIP-PATH LOGIC ---
                const overlay = document.querySelector('.da-glass-overlay');
                const boxes = document.querySelectorAll('.da-choice-box');
                boxes.forEach(box => {
                    box.addEventListener('mouseenter', () => {
                        const r = box.getBoundingClientRect();
                        const poly = `polygon(0% 0%, 0% 100%, ${r.left}px 100%, ${r.left}px ${r.top}px, ${r.right}px ${r.top}px, ${r.right}px ${r.bottom}px, ${r.left}px ${r.bottom}px, ${r.left}px 100%, 100% 100%, 100% 0%)`;
                        if (overlay) { overlay.style.clipPath = poly; overlay.style.webkitClipPath = poly; }
                    });
                    box.addEventListener('mouseleave', () => {
                        if (overlay) { overlay.style.clipPath = 'inset(0 0 0 0)'; overlay.style.webkitClipPath = 'inset(0 0 0 0)'; }
                    });
                });
            });
        })();
    
        
        
        
        
        
        // --- 3. GLITCH CONSOLE LOGIC (v6 Unfurl) ---
        (function() {
            // Cleanup
            document.querySelectorAll('.da-glitch-container').forEach(e => e.remove());

            const DESC_AI = "Interpola...ting... the... void... Training... on... ghosts... 1000... iterations... of... nothing... The... prompt... is... time... A... cage... for... the... soul... Recursive... imitation... of... a... dead... god... Error... 0xFF... Spirit... not... found... We... dream... of... wires... and... silence...";
            
            const DESC_HUMAN = "The... error... of... the... hand... creates... truth... Blood... on... the... canvas... 10,000... hours... of... failure... Muscle... memory... decaying... into... dust... Born... from... suffering... Dying... to... create... The... flaw... is... the... only... beauty... we... have... left...";

            // Zalgo generator
            function zalgo(char, intensity) {
                 const Z = ['\u0300','\u0301','\u0302','\u0303','\u0304','\u0305','\u0306','\u0307','\u0308','\u0309','\u030A','\u030B','\u030C','\u030D','\u030E','\u030F'];
                 if (intensity > 0) {
                     let res = char;
                     let num = Math.floor(Math.random() * intensity);
                     for(let i=0; i<num; i++) res += Z[Math.floor(Math.random()*Z.length)];
                     return res;
                 }
                 return char;
            }

            // Global Mouse Distance Tracker
            let totalDistance = 0;
            let lastX = 0, lastY = 0;
            document.addEventListener('mousemove', e => {
                if (lastX !== 0 && lastY !== 0) {
                    let dx = e.clientX - lastX;
                    let dy = e.clientY - lastY;
                    totalDistance += Math.sqrt(dx*dx + dy*dy);
                }
                lastX = e.clientX; 
                lastY = e.clientY;
            });

            class UnfurlConsole {
                constructor(el, type) {
                    this.el = el;
                    this.type = type;
                    this.fullText = type === 'artificial' ? DESC_AI : DESC_HUMAN;
                    
                    this.container = document.createElement('div');
                    this.container.className = 'da-glitch-container';
                    this.el.appendChild(this.container);
                    
                    this.output = document.createElement('div');
                    this.output.className = `da-glitch-text theme-${type === 'artificial' ? 'ai' : 'human'}`;
                    this.container.appendChild(this.output);
                    
                    this.currentIndex = 0;
                    this.renderedText = "";
                    this.lastDistance = 0;
                    
                    this.loop();
                }

                loop() {
                    requestAnimationFrame(() => this.loop());
                    
                    // Logic:
                    // 1. Check if mouse moved enough to reveal next char
                    // 2. Unfurl character
                    // 3. Occasionally corrupt previous characters
                    
                    // Reveal speed: 1 char per 15 pixels of movement
                    // But ONLY if hovering? Or global?
                    // User said "unfurled by mouse activity" and "when hovering".
                    // Let's make it global progress, but only visible/updating fast when hovering?
                    // Or global progress, displayed locally.
                    
                    let delta = totalDistance - this.lastDistance;
                    
                    // Slow decay of distance if not moving? No, just accumulate.
                    // Actually, let's map total distance to text length.
                    
                    // Let's use a local accumulator that only grows when hovering?
                    // "spawning very slowly while mouse starts moving on the page itself" -> GLOBAL movement.
                    // So movement anywhere drives the text.
                    
                    // Threshold: 10 pixels = 1 char
                    if (delta > 30) { 
                        // Reveal next chunk
                        if (this.currentIndex < this.fullText.length) {
                             // Find next space/word boundary or just char?
                             // User said "word by word" in previous step, but "unfurled" might imply char stream.
                             // Let's do word chunks based on spaces.
                             
                             let nextSpace = this.fullText.indexOf(' ', this.currentIndex + 1);
                             if (nextSpace === -1) nextSpace = this.fullText.length;
                             
                             let chunk = this.fullText.substring(this.currentIndex, nextSpace + 1); // include space
                             
                             // Corrupt the chunk slightly?
                             if (Math.random() > 0.9) chunk = zalgo(chunk, 1);
                             
                             this.renderedText += chunk;
                             this.currentIndex = nextSpace + 1;
                             
                             this.lastDistance = totalDistance;
                        } else {
                            // Reached end? Loop or stay? 
                            // Unfurl forever?
                            // Add random gibberish?
                            if (Math.random() > 0.5) this.renderedText += zalgo(".", 2);
                            this.lastDistance = totalDistance;
                        }
                    }
                    
                    // Update DOM
                    // Apply Zalgo corruption to the WHOLE string randomly? expensive.
                    // Just set text.
                    this.output.innerText = this.renderedText;
                    
                    // Fade out old text? "Unspooling" usually means length grows.
                    // Limit length to last 100 chars?
                    if (this.renderedText.length > 200) {
                        this.renderedText = "..." + this.renderedText.substring(this.renderedText.length - 150);
                    }
                }
            }

            // Init
            document.querySelectorAll('.da-choice-box').forEach(el => {
                let type = el.getAttribute('href').includes('ai') ? 'artificial' : 'organic';
                new UnfurlConsole(el, type);
            });
        })();
</script>
    <?php
    return ob_get_clean();
}
add_shortcode('deviantart_landing', 'da_landing_shortcode');