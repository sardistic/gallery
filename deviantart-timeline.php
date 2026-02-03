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
        footer, #colophon, .site-footer, .elementor-location-footer { display: none !important; }
        
        /* Ensure Header is visible and top-most */
        header, #masthead, .site-header, #wpadminbar, .elementor-location-header, #header { 
            /* display: block !important; */
            position: relative; 
            z-index: 9999 !important; 
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* Body Transparent to show Canvas (-1), HTML provides Black Base */
        html { background: #050505 !important; height: 100% !important; }
        body { margin: 0 !important; padding: 0 !important; overflow: hidden !important; background: transparent !important; height: 100% !important; }

        /* Ensure content sits ABOVE the glass */
        .da-landing-wrapper {
            position: fixed; top: 0; left: 0;
            width: 100vw; height: 100vh;
            display: flex; align-items: center; justify-content: center;
            gap: 40px; z-index: 10;
        }

        /* Canvas at Z-1 (Standard Background) */
        canvas#da-webgl {
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: -1; 
            pointer-events: none;
            opacity: 1 !important;
        }

        .da-choice-box {
            width: 300px; height: 300px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 700; color: #fff; text-decoration: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            transition: all 0.4s ease; cursor: pointer;
            font-family: 'Inter', system-ui, sans-serif;
            position: relative; overflow: hidden; pointer-events: auto;
        }
        .da-choice-box:hover {
            transform: translateY(-10px) scale(1.05);
            background: rgba(0,0,0,0.4); 
            backdrop-filter: none; -webkit-backdrop-filter: none;
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
        }
        .da-choice-box::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: 0.5s;
        }
        .da-choice-box:hover::after { left: 100%; }

        @media (max-width: 700px) { .da-landing-wrapper { flex-direction: column; } }

        /* GLOBAL VIGNETTE */
        .da-vignette {
            position: fixed; inset: 0; pointer-events: none; z-index: 0; 
            background: radial-gradient(circle at center, transparent 20%, #000 120%);
        }

        /* GLOBAL GLASS OVERLAY */
        .da-glass-overlay {
            position: fixed; inset: 0; width: 100vw; height: 100vh;
            z-index: 1; pointer-events: none;
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            background: rgba(0,0,0,0.01);
            transition: clip-path 0.4s cubic-bezier(0.2, 0.8, 0.2, 1), -webkit-clip-path 0.4s;
            -webkit-clip-path: inset(0 0 0 0); clip-path: inset(0 0 0 0);
        }
    </style>

    <!-- HTML STRUCTURE -->
    <div class="da-vignette"></div>
    <div class="da-glass-overlay"></div>

    <div class="da-landing-wrapper">
        <a href="https://sardistic.com/ai/" class="da-choice-box">AI</a>
        <a href="https://sardistic.com/gallery-timeline/" class="da-choice-box">Not AI</a>
    </div>

    <canvas id="da-webgl"></canvas>

    <!-- SHADERS -->
    <script id="vs" type="x-shader/x-vertex">
        attribute vec2 position; varying vec2 vUv;
        void main() { vUv = position; gl_Position = vec4(position, 0.0, 1.0); }
    </script>
    <script id="fs" type="x-shader/x-fragment">
        precision mediump float; uniform float uTime; uniform vec2 uResolution; uniform vec2 uMouse;
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
        float pattern(in vec2 p, out vec2 q, out vec2 r, float t, float w) {
            q.x=fbm(p); q.y=fbm(p+vec2(5.2,1.3));
            r.x=fbm(p+4.0*q*w+vec2(1.7,9.2)+0.15*t);
            r.y=fbm(p+4.0*q*w+vec2(8.3,2.8)+0.126*t);
            return fbm(p+4.0*r+vec2(0.0,-0.4*t));
        }
        void main() {
            vec2 uv = gl_FragCoord.xy/uResolution.xy; uv = uv*2.0-1.0; uv.x *= uResolution.x/uResolution.y;
            uv.x = abs(uv.x); uv.x -= 0.1*(1.0-uv.y)*0.5;
            float t = uTime*0.2; float mX = uMouse.x/uResolution.x; float mY = uMouse.y/uResolution.y;
            
            // FIX: Constant Density
            float w = 0.2 + mX*0.5; 
            float dIn = 0.8 + mY*0.5; 
            
            vec2 p = uv*1.6; vec2 q,r; float f = pattern(p,q,r,t,w);
            float ink = f * length(r); ink = pow(ink, 1.5) * 2.5;
            float dist = length(uv); float mask = smoothstep(1.8, 0.2, dist); ink *= mask * dIn;
            float core = smoothstep(1.0, 0.0, dist + f*0.5); ink += core * 0.3 * dIn; ink = clamp(ink, 0.0, 1.0);
            vec3 color = vec3(ink * 0.95); color = mix(vec3(0.02), vec3(0.95), ink);
            gl_FragColor = vec4(color, 1.0);
        }
    </script>
    
    <!-- SAFE MODE JS: IIFE + No Cache -->
    <script data-cfasync="false">
        (function() {
            document.addEventListener('DOMContentLoaded', function () {
                // WEBGL SETUP
                const c = document.getElementById('da-webgl'); 
                if (!c) return;
                const g = c.getContext('webgl');
                if (!g) return;
                
                const r = () => { c.width = window.innerWidth; c.height = window.innerHeight; g.viewport(0, 0, c.width, c.height); };
                window.addEventListener('resize', r); r();
                
                const cs = (g, t, s) => { const h = g.createShader(t); g.shaderSource(h, s); g.compileShader(h); return h; };
                const p = g.createProgram();
                g.attachShader(p, cs(g, g.VERTEX_SHADER, document.getElementById('vs').innerText));
                g.attachShader(p, cs(g, g.FRAGMENT_SHADER, document.getElementById('fs').innerText));
                g.linkProgram(p); g.useProgram(p);
                
                const b = g.createBuffer(); g.bindBuffer(g.ARRAY_BUFFER, b);
                g.bufferData(g.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]), g.STATIC_DRAW);
                
                const pl = g.getAttribLocation(p, "position"); g.enableVertexAttribArray(pl); g.vertexAttribPointer(pl, 2, g.FLOAT, false, 0, 0);
                const ut = g.getUniformLocation(p, 'uTime'); const ur = g.getUniformLocation(p, 'uResolution'); const um = g.getUniformLocation(p, 'uMouse');
                
                let mx = window.innerWidth / 2, my = window.innerHeight / 2;
                window.addEventListener('mousemove', e => { mx = e.clientX; my = window.innerHeight - e.clientY; });
                
                const ren = (t) => { g.uniform1f(ut, t * 0.001); g.uniform2f(ur, c.width, c.height); g.uniform2f(um, mx, my); g.drawArrays(g.TRIANGLES, 0, 6); requestAnimationFrame(ren); };
                requestAnimationFrame(ren);
    
                // CLIP-PATH PORTAL LOGIC
                const overlay = document.querySelector('.da-glass-overlay');
                const boxes = document.querySelectorAll('.da-choice-box');
    
                boxes.forEach(box => {
                    box.addEventListener('mouseenter', () => {
                        const r = box.getBoundingClientRect();
                        const poly = `polygon(0% 0%, 0% 100%, ${r.left}px 100%, ${r.left}px ${r.top}px, ${r.right}px ${r.top}px, ${r.right}px ${r.bottom}px, ${r.left}px ${r.bottom}px, ${r.left}px 100%, 100% 100%, 100% 0%)`;
                        overlay.style.clipPath = poly;
                        overlay.style.webkitClipPath = poly;
                    });
    
                    box.addEventListener('mouseleave', () => {
                        overlay.style.clipPath = 'inset(0 0 0 0)';
                        overlay.style.webkitClipPath = 'inset(0 0 0 0)';
                    });
                });
            });
        })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('deviantart_landing', 'da_landing_shortcode');