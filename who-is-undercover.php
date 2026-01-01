<?php
// ==========================================================
// 后端配置区域 (请在此处配置 AI API)
// ==========================================================
$apiKey = 'sk-你的KEY'; // 替换你的 API Key (OpenAI / DeepSeek 等)
$apiUrl = 'https://****/v1/chat/completions'; // 替换对应的 API 地址
$model  = ''; // 替换模型名称

// 处理 AJAX 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ai_generate') {
    header('Content-Type: application/json');

    $topic = $_POST['topic'] ?? '随机日常物品';
    $count = intval($_POST['count'] ?? 10);
    
    // 限制最大数量防止超时
    if ($count > 50) $count = 50; 
    if ($count < 1) $count = 1;

    // 构建提示词
    $prompt = "请生成 {$count} 组'谁是卧底'的游戏词汇。
    主题是：{$topic}。
    要求：
    1. 每组包含一个平民词和一个卧底词，词意相近但不同。
    2. 格式严格为：平民词,卧底词
    3. 每行一组，不要出现序号、标题或其他任何字符。
    4. 不要包含任何Markdown格式。";

    $data = [
        "model" => $model,
        "messages" => [
            ["role" => "system", "content" => "你是一个谁是卧底游戏出题助手。只返回纯文本数据。"],
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 1.0
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // 忽略 SSL 验证（本地测试用，生产环境建议开启）
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        echo json_encode(['status' => 'error', 'message' => 'Curl error: ' . curl_error($ch)]);
        exit;
    }
    curl_close($ch);

    $json = json_decode($response, true);
    
    if (isset($json['choices'][0]['message']['content'])) {
        $content = trim($json['choices'][0]['message']['content']);
        // 解析返回的文本
        $lines = explode("\n", $content);
        $pairs = [];
        foreach ($lines as $line) {
            $parts = explode(",", str_replace('，', ',', trim($line))); // 兼容中英文逗号
            if (count($parts) >= 2) {
                $pairs[] = ['civ' => trim($parts[0]), 'spy' => trim($parts[1])];
            }
        }
        echo json_encode(['status' => 'success', 'data' => $pairs]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'API 响应格式错误或 Key 无效', 'raw' => $response]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>谁是卧底打印助手 - AI 盲盒版</title>
    <style>
        :root {
            --bg-dark: #1a2233;
            --panel-bg: #252e42;
            --accent-gold: #d4a650;
            --btn-gray: #3e4658;
            --text-white: #ffffff;
            --civ-orange: #f39c12;
            --spy-red: #e74c3c;
            --ai-blue: #3498db;
        }

        /* =========================================
           打印专用样式 (核心功能)
           ========================================= */
        @media print {
            @page { 
                size: landscape; 
                margin: 0.4cm; 
            }
            .no-print { display: none !important; }
            body { 
                background: white !important; 
                padding: 0 !important; 
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-break { page-break-after: always; }
            thead { display: none !important; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 0 !important; }
            td { border: 1px solid #333 !important; height: 1.35cm; font-size: 15px !important; color: black !important; }
            .truth-column { border-left: 10px solid #000 !important; font-weight: bold; width: 150px; }
            .spy-hint { color: var(--spy-red) !important; font-weight: normal; }
            .print-guide-marker {
                display: block !important;
                font-size: 12px; color: #666; text-align: center;
                border: none !important; height: auto !important; padding: 2px 0;
            }
            /* 打印时取消遮罩，确保清晰 */
            #print-area { filter: none !important; opacity: 1 !important; visibility: visible !important; }
            .blind-mask-overlay { display: none !important; }
        }

        /* =========================================
           基础页面布局
           ========================================= */
        body {
            font-family: "PingFang SC", "Microsoft YaHei", sans-serif;
            margin: 0; padding: 0;
            background-color: var(--bg-dark);
            color: var(--text-white);
            display: flex; flex-direction: column; align-items: center;
        }

        .setup-container {
            width: 95%; max-width: 900px;
            padding: 20px 10px; text-align: center;
        }

        .title {
            color: var(--accent-gold);
            font-size: 32px; font-weight: bold;
            margin-bottom: 10px; letter-spacing: 2px;
        }

        /* 词语输入区域 */
        .middle-pillar-wrapper {
            display: flex;
            background: var(--panel-bg);
            border-radius: 12px;
            border: 1px solid #3e4658;
            margin-bottom: 20px;
            overflow: hidden;
            height: 200px;
            position: relative;
        }
        textarea {
            flex: 1; background: transparent; border: none;
            color: white; padding: 10px; font-size: 14px; 
            line-height: 24px; resize: none; outline: none;
        }
        #spyIn { text-align: right; border-right: 1px solid #3e4658; }
        #civIn { text-align: left; border-left: 1px solid #3e4658; }

        .line-pillar {
            width: 40px; background: rgba(0,0,0,0.2);
            display: flex; flex-direction: column;
            padding: 10px 0; color: var(--accent-gold);
            font-size: 12px; line-height: 24px; user-select: none;
            overflow: hidden;
        }

        .ratio-bar-container {
            width: 200px; height: 12px;
            margin: 10px auto; display: flex; gap: 3px;
        }
        .bar-segment { flex: 1; height: 100%; border-radius: 2px; }
        .seg-civ { background-color: var(--civ-orange); }
        .seg-spy { background-color: var(--spy-red); }

        /* =========================================
           AI 盲盒区域 (新增)
           ========================================= */
        .ai-box {
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(26, 34, 51, 0.5) 100%);
            border: 1px solid var(--ai-blue);
            border-radius: 12px; padding: 15px; margin-bottom: 20px;
            display: flex; gap: 15px; align-items: center;
            box-shadow: 0 0 15px rgba(52, 152, 219, 0.2);
        }
        .ai-input {
            background: rgba(0,0,0,0.3); border: 1px solid var(--btn-gray);
            color: white; padding: 10px; border-radius: 6px; font-size: 14px; outline: none;
        }
        .ai-input:focus { border-color: var(--ai-blue); }
        .btn-ai {
            background-color: var(--ai-blue); color: white;
            border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer;
            font-weight: bold; white-space: nowrap;
            transition: 0.3s;
            display: flex; align-items: center; gap: 5px;
        }
        .btn-ai:hover { box-shadow: 0 0 10px var(--ai-blue); }
        .btn-ai:disabled { background-color: #555; cursor: not-allowed; }

        /* =========================================
           控制面板
           ========================================= */
        .control-panel {
            display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px;
        }
        .num-controls-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px;
        }
        .control-card {
            background: var(--panel-bg); border-radius: 12px; padding: 15px;
            display: flex; flex-direction: column; align-items: center; justify-content: space-between;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05);
        }
        .control-label { font-size: 14px; color: #8fa1b3; margin-bottom: 10px; font-weight: bold; }
        .stepper-capsule {
            display: flex; align-items: center; background: #1a2233;
            border-radius: 20px; padding: 2px; border: 1px solid #3e4658;
        }
        .btn-step {
            width: 32px; height: 32px; border-radius: 50%; border: none;
            background-color: var(--btn-gray); color: white;
            font-size: 18px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .btn-step:hover { background-color: var(--accent-gold); color: #222; }
        .stepper-value { min-width: 50px; text-align: center; font-size: 18px; font-weight: bold; color: var(--text-white); }

        .print-settings-row {
            background: var(--panel-bg); border-radius: 12px; padding: 10px 15px;
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 15px; border: 1px solid rgba(255,255,255,0.05);
        }
        .toggles-group { display: flex; gap: 15px; align-items: center; }
        .toggle-item { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .toggle-text { font-size: 13px; color: #ccc; }
        .toggle-input { display: none; }
        .toggle-slider {
            position: relative; width: 40px; height: 22px; 
            background-color: #3e4658; border-radius: 22px; transition: .3s;
        }
        .toggle-slider:before {
            position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px;
            background-color: white; border-radius: 50%; transition: .3s;
        }
        .toggle-input:checked + .toggle-slider { background-color: var(--accent-gold); }
        .toggle-input:checked + .toggle-slider:before { transform: translateX(18px); }

        .select-wrapper { position: relative; min-width: 110px; }
        .select-wrapper::after {
            content: '▼'; font-size: 10px; color: var(--accent-gold);
            position: absolute; right: 10px; top: 50%;
            transform: translateY(-50%); pointer-events: none;
        }
        select {
            width: 100%; appearance: none; background: #1a2233; color: white;
            border: 1px solid #3e4658; padding: 8px 25px 8px 10px; 
            border-radius: 8px; font-size: 13px; outline: none;
            cursor: pointer; transition: border 0.3s;
        }
        select:hover, select:focus { border-color: var(--accent-gold); }

        .btn-start {
            background-color: var(--accent-gold); color: #222; border: none; 
            padding: 8px 25px; font-size: 15px; font-weight: bold;
            border-radius: 20px; cursor: pointer;
            box-shadow: 0 4px 10px rgba(212, 166, 80, 0.3);
            transition: transform 0.2s, box-shadow 0.2s; white-space: nowrap;
        }
        .btn-start:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(212, 166, 80, 0.4); }

        /* =========================================
           屏幕盲盒遮挡层 (Screen Blind Mask)
           ========================================= */
        #print-area { 
            background: white; color: black; width: 100%; 
            transition: all 0.5s;
        }
        
        /* 盲盒模式激活时：屏幕模糊且全遮挡 */
        .blind-active #print-area {
            filter: blur(20px) grayscale(100%);
            opacity: 0.1;
            pointer-events: none;
            user-select: none;
        }
        .blind-mask-overlay {
            display: none;
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            flex-direction: column; align-items: center; justify-content: center;
            color: var(--accent-gold);
            text-align: center;
        }
        .blind-active .blind-mask-overlay { display: flex; }
        .blind-text { font-size: 30px; font-weight: bold; margin-bottom: 20px; }
        .blind-sub { color: #fff; font-size: 16px; margin-bottom: 30px; max-width: 600px; line-height: 1.6; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 50px; }
        th, td { border: 1px solid #333; padding: 10px 5px; text-align: center; font-size: 14px;color: #979797; }
        th { background: #f0f0f0; color: #333; height: 35px; }
        .truth-column { border-left: 10px solid #000 !important; font-weight: bold; width: 160px; }
        .spy-hint { color: var(--spy-red); margin-left: 3px; font-weight: normal; }
        
        .back-cell {
            background-color: transparent !important;
            background-image: repeating-linear-gradient(45deg, #ccc 0, #ccc 1px, transparent 0, transparent 5px) !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .print-guide-marker { display: none; }

        /* Loader */
        .loader {
            border: 3px solid rgba(255,255,255,0.1); border-radius: 50%;
            border-top: 3px solid var(--ai-blue); width: 20px; height: 20px;
            animation: spin 1s linear infinite; display: none;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

<!-- 盲盒全屏遮罩 -->
<div class="blind-mask-overlay no-print" id="blindOverlay">
    <div class="blind-text">🙈 绝密盲盒模式 🙈</div>
    <div class="blind-sub">
        词条已生成并隐藏，请直接进行打印。<br>
        打印出来的纸张上会有清晰的内容。<br>
        <span style="color:#e74c3c">出题人也不许偷看哦！</span>
    </div>
    <button class="btn-start" onclick="window.print()">🖨️ 立即打印</button>
    <br>
    <button style="background:transparent; border:1px solid #666; color:#888; padding:5px 15px; border-radius:15px; cursor:pointer; margin-top:20px" onclick="exitBlindMode()">退出盲盒模式</button>
</div>

<div class="setup-container no-print">
    <div class="title">谁是卧底 · 打印助手</div>
    
    <!-- AI 盲盒输入 -->
    <div class="ai-box">
        <div style="font-weight:bold; color:var(--ai-blue)">🤖 AI 盲盒</div>
        <input type="text" id="aiTopic" class="ai-input" placeholder="输入主题 (如: 水果/生活物品)" style="flex:1">
        <input type="number" id="aiCount" class="ai-input" placeholder="数量" value="10" min="1" max="50" style="width:60px">
        <button class="btn-ai" onclick="callAI()" id="btnAi">
            <div class="loader" id="aiLoader"></div>
            <span>生成并全盲打印</span>
        </button>
    </div>

    <!-- 词语输入 (手动模式) -->
    <div class="middle-pillar-wrapper">
        <textarea id="spyIn" oninput="initLines()" onscroll="syncScroll(this, 'civIn', 'linePillar')" placeholder="卧底词(每行一个)"></textarea>
        <div class="line-pillar" id="linePillar"></div>
        <textarea id="civIn" oninput="initLines()" onscroll="syncScroll(this, 'spyIn', 'linePillar')" placeholder="平民词(每行一个)"></textarea>
    </div>

    <div class="ratio-bar-container" id="ratioBar"></div>

    <!-- 控制面板 -->
    <div class="control-panel">
        
        <div class="num-controls-row">
            <div class="control-card">
                <div class="control-label">玩家总数 (4-10)</div>
                <div class="stepper-capsule">
                    <button class="btn-step" onclick="changeVal('pCount', -1)">−</button>
                    <div id="pCountText" class="stepper-value">6</div>
                    <button class="btn-step" onclick="changeVal('pCount', 1)">+</button>
                </div>
            </div>
            <div class="control-card">
                <div class="control-label">卧底人数 (1-3)</div>
                <div class="stepper-capsule">
                    <button class="btn-step" onclick="changeVal('sCount', -1)">−</button>
                    <div id="sCountText" class="stepper-value">1</div>
                    <button class="btn-step" onclick="changeVal('sCount', 1)">+</button>
                </div>
            </div>
            <div class="control-card">
                <div class="control-label">卧底白板率 %</div>
                <div class="stepper-capsule">
                    <button class="btn-step" onclick="changeVal('bProb', -5)">−</button>
                    <div id="bProbText" class="stepper-value">10%</div>
                    <button class="btn-step" onclick="changeVal('bProb', 5)">+</button>
                </div>
            </div>
        </div>

        <div class="print-settings-row">
            <div class="toggles-group">
                <label class="toggle-item">
                    <input type="checkbox" id="isRandom" class="toggle-input" checked>
                    <div class="toggle-slider"></div>
                    <span class="toggle-text">乱序</span>
                </label>
                
                <label class="toggle-item">
                    <input type="checkbox" id="genBack" class="toggle-input" checked>
                    <div class="toggle-slider"></div>
                    <span class="toggle-text">背面</span>
                </label>
            </div>

            <div class="select-wrapper">
                <select id="printMode">
                    <option value="interleaved">自动双面</option>
                    <option value="separated">手动双面</option>
                </select>
            </div>

            <button class="btn-start" onclick="generate()">手动生成预览</button>
        </div>
    </div>
</div>

<div id="print-area"></div>

<script>
    let config = { pCount: 6, sCount: 1, bProb: 10 };
    const ROWS_PER_PAGE = 10; 

    // --- AI 相关逻辑 ---
    function callAI() {
        const topic = document.getElementById('aiTopic').value.trim();
        const count = document.getElementById('aiCount').value;
        const btn = document.getElementById('btnAi');
        const loader = document.getElementById('aiLoader');

        if(!topic) { alert("请输入主题"); return; }

        // UI Loading
        btn.disabled = true;
        loader.style.display = 'block';

        // 发送 AJAX 请求到当前 PHP 文件
        const formData = new FormData();
        formData.append('action', 'ai_generate');
        formData.append('topic', topic);
        formData.append('count', count);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                // 进入盲盒模式：不填充 Textarea，直接生成打印HTML并隐藏
                renderBlind(data.data);
            } else {
                alert('生成失败: ' + (data.message || '未知错误'));
            }
        })
        .catch(err => {
            alert('请求错误: ' + err);
        })
        .finally(() => {
            btn.disabled = false;
            loader.style.display = 'none';
        });
    }

    // 渲染盲盒内容 (核心逻辑：复用 generate 但数据来源不同)
    function renderBlind(pairs) {
        let spyList = [];
        let civList = [];
        pairs.forEach(p => {
            spyList.push(p.spy);
            civList.push(p.civ);
        });

        // 1. 生成打印HTML
        buildPrintHTML(spyList, civList);

        // 2. 激活 CSS 盲盒遮罩 (屏幕不可见)
        document.body.classList.add('blind-active');

        // 3. 自动触发打印
        setTimeout(() => {
            window.print();
        }, 500);
    }

    function exitBlindMode() {
        document.body.classList.remove('blind-active');
        document.getElementById('print-area').innerHTML = ''; // 清除秘密内容
    }

    // --- 原有逻辑 ---
    function syncScroll(source, t1, t2) {
        document.getElementById(t1).scrollTop = source.scrollTop;
        document.getElementById(t2).scrollTop = source.scrollTop;
    }

    function initLines() {
        const pillar = document.getElementById('linePillar');
        const spyLines = document.getElementById('spyIn').value.split('\n').length;
        const civLines = document.getElementById('civIn').value.split('\n').length;
        const maxLines = Math.max(spyLines, civLines, 10);

        pillar.innerHTML = '';
        for(let i=1; i<=maxLines; i++) {
            const div = document.createElement('div');
            div.innerText = i;
            pillar.appendChild(div);
        }
    }

    function changeVal(key, delta) {
        if (key === 'pCount') {
            config.pCount = Math.max(4, Math.min(10, config.pCount + delta));
            validateSpyCount();
        } else if (key === 'sCount') {
            config.sCount = Math.max(1, Math.min(3, config.sCount + delta));
            validateSpyCount();
        } else if (key === 'bProb') {
            config.bProb = Math.max(0, Math.min(100, config.bProb + delta));
        }
        updateUI();
    }

    function validateSpyCount() {
        let maxSpy = (config.pCount >= 8) ? 3 : (config.pCount >= 6 ? 2 : 1);
        if (config.sCount > maxSpy) config.sCount = maxSpy;
    }

    function updateUI() {
        document.getElementById('pCountText').innerText = config.pCount;
        document.getElementById('sCountText').innerText = config.sCount;
        document.getElementById('bProbText').innerText = config.bProb + '%';
        const bar = document.getElementById('ratioBar');
        bar.innerHTML = '';
        for(let i=0; i<config.pCount-config.sCount; i++) bar.innerHTML += '<div class="bar-segment seg-civ"></div>';
        for(let i=0; i<config.sCount; i++) bar.innerHTML += '<div class="bar-segment seg-spy"></div>';
    }

    // 手动生成入口
    function generate() {
        const spyList = document.getElementById('spyIn').value.trim().split('\n').filter(x => x.trim() !== "");
        const civList = document.getElementById('civIn').value.trim().split('\n').filter(x => x.trim() !== "");
        
        if (spyList.length !== civList.length || spyList.length === 0) { 
            alert("行数不匹配或内容为空！"); 
            return; 
        }
        
        // 确保退出盲盒模式，显示内容
        document.body.classList.remove('blind-active');
        buildPrintHTML(spyList, civList);
        
        const printArea = document.getElementById('print-area');
        window.scrollTo({ top: printArea.offsetTop, behavior: 'smooth' });
    }

    // 公共构建逻辑
    function buildPrintHTML(spyList, civList) {
        const isRandom = document.getElementById('isRandom').checked;
        const genBack = document.getElementById('genBack').checked;
        const printMode = document.getElementById('printMode').value;
        const printArea = document.getElementById('print-area');

        printArea.innerHTML = '';

        const totalRows = spyList.length;
        const pageCount = Math.ceil(totalRows / ROWS_PER_PAGE);
        let pagesData = [];

        for (let p = 0; p < pageCount; p++) {
            const startIdx = p * ROWS_PER_PAGE;
            const endIdx = Math.min(startIdx + ROWS_PER_PAGE, totalRows);
            const currentPageRowsCount = endIdx - startIdx;

            // --- 正面 ---
            let frontHtml = `<div class="page-break">`;
            if(printMode === 'separated') frontHtml += `<div class="print-guide-marker">--- 第 ${p+1} 页 (正面) ---</div>`;
            
            frontHtml += `<table><thead><tr>`;
            frontHtml += `<th class="truth-column">真相</th>`; 
            for (let i = 1; i <= config.pCount; i++) frontHtml += `<th>P${i}</th>`;
            frontHtml += '</tr></thead><tbody>';

            for (let i = startIdx; i < endIdx; i++) {
                let roles = [];
                let isBlankGame = Math.random() < (config.bProb / 100);
                for (let s = 0; s < config.sCount; s++) roles.push((isBlankGame && s === 0) ? " [ 空白 ] " : spyList[i].trim());
                for (let c = 0; c < (config.pCount - config.sCount); c++) roles.push(civList[i].trim());

                if (isRandom) roles.sort(() => Math.random() - 0.5);

                frontHtml += `<tr><td class="truth-column">${spyList[i].trim()} <span class="spy-hint">(${config.sCount})</span></td>`;
                roles.forEach(r => frontHtml += `<td>${r}</td>`);
                frontHtml += `</tr>`;
            }
            frontHtml += '</tbody></table></div>';

            // --- 背面 ---
            let backHtml = '';
            if (genBack) {
                backHtml += `<div class="page-break">`;
                if(printMode === 'separated') backHtml += `<div class="print-guide-marker">--- 第 ${p+1} 页 (背面) ---</div>`;
                
                backHtml += `<table><thead><tr>`;
                for (let i = config.pCount; i >= 1; i--) backHtml += `<th>P${i}</th>`;
                backHtml += `<th class="truth-column">标记</th></tr></thead><tbody>`;
                
                for (let i = 0; i < currentPageRowsCount; i++) {
                    backHtml += '<tr>';
                    for (let j = 0; j < config.pCount; j++) backHtml += '<td class="back-cell"></td>';
                    backHtml += `<td class="truth-column">${config.pCount} 人局</td></tr>`;
                }
                backHtml += '</tbody></table></div>';
            }
            pagesData.push({ front: frontHtml, back: backHtml });
        }

        // --- 组合 ---
        if (printMode === 'separated' && genBack) {
            pagesData.forEach(page => printArea.innerHTML += page.front);
            pagesData.forEach(page => printArea.innerHTML += page.back);
        } else {
            pagesData.forEach(page => {
                printArea.innerHTML += page.front;
                if (genBack) printArea.innerHTML += page.back;
            });
        }
    }

    window.onload = function() {
        initLines();
        updateUI();
    };
</script>
</body>
</html>