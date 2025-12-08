(function($){
    const data = window.RPGSkillTreesData || {};
    let selectedTrees = [];
    let selectedSkills = {};
    let svg;

    function findSkillByInstance(instanceId){
        return data.skills.find(s=>s.instance === instanceId);
    }

    function findSkillInstance(baseId, treeId){
        return data.skills.find(s=>s.id === parseInt(baseId,10) && s.tree === parseInt(treeId,10));
    }

    function getInstanceIdForSkill(baseId, treeId){
        const instance = findSkillInstance(baseId, treeId);
        return instance ? instance.instance : null;
    }

    function normalizeLoadedSkills(rawSkills){
        const normalized = {};
        if(!rawSkills) return normalized;
        Object.keys(rawSkills).forEach(key=>{
            if(!rawSkills[key]) return;
            if(key.includes(':')){
                if(findSkillByInstance(key)){
                    normalized[key] = true;
                }
                return;
            }
            const baseId = parseInt(key,10);
            const preferred = data.skills.find(s=>s.id===baseId && selectedTrees.includes(s.tree));
            if(preferred){
                normalized[preferred.instance] = true;
                return;
            }
            const fallback = data.skills.find(s=>s.id===baseId);
            if(fallback){
                normalized[fallback.instance] = true;
            }
        });
        return normalized;
    }

    function init(){
        svg = document.getElementById('rpg-prereq-lines');
        renderTreeSelector();
        renderPointSummary();
        bindActions();
    }

    function renderTreeSelector(){
        const list = $('#rpg-tree-list');
        list.empty();
        data.trees.forEach(tree => {
            const id = 'rpg-tree-'+tree.id;
            const checkbox = $('<label class="rpg-tree-option"><input type="checkbox" value="'+tree.id+'" id="'+id+'"> '+tree.name+'</label>');
            list.append(checkbox);
        });
        list.find('input').on('change', function(){
            const val = parseInt($(this).val(),10);
            if($(this).is(':checked')){
                if(!selectedTrees.includes(val)) selectedTrees.push(val);
            } else {
                selectedTrees = selectedTrees.filter(t=>t!==val);
                Object.keys(selectedSkills).forEach(key=>{
                    const skill = findSkillByInstance(key);
                    if(skill && skill.tree === val){
                        delete selectedSkills[key];
                    }
                });
            }
            renderBuilder();
        });
    }

    function groupSkillsByTree(){
        const grouped = {};
        data.skills.forEach(skill=>{
            if(!grouped[skill.tree]) grouped[skill.tree] = {};
            if(!grouped[skill.tree][skill.tier]) grouped[skill.tree][skill.tier] = [];
            grouped[skill.tree][skill.tier].push(skill);
        });
        return grouped;
    }

    function renderBuilder(){
        const body = $('#rpg-builder-body');
        body.empty();
        const grouped = groupSkillsByTree();
        selectedTrees.forEach(treeId=>{
            const tree = data.trees.find(t=>t.id===treeId);
            if(!tree) return;
            const treeWrap = $('<div class="rpg-tree" data-tree="'+treeId+'"></div>');
            treeWrap.append('<h3>'+tree.name+'</h3>');
            const tiersWrap = $('<div class="rpg-tiers"></div>');
            const layout = calculateLayoutForTree(treeId);
            for(let tier=1;tier<=4;tier++){
                const tierColumn = $('<div class="rpg-tier-column" data-tier="'+tier+'"></div>');
                tierColumn.append('<div class="rpg-tier-title">'+dataLabel('Tier')+' '+tier+'</div>');
                const tierCol = $('<div class="rpg-tier" data-tier="'+tier+'"></div>');
                const skills = (grouped[treeId] && grouped[treeId][tier] ? grouped[treeId][tier] : []);
                const paddingOffset = 12;
                const paddingBottom = 12;
                tierCol.css('min-height', (layout.totalRows * layout.rowHeight + paddingOffset + paddingBottom)+'px');
                tierCol.css('padding-top', paddingOffset+'px');
                tierCol.css('padding-bottom', paddingBottom+'px');
                skills.forEach(skill=>{
                    const skillNode = $('<div class="rpg-skill" data-instance="'+skill.instance+'" data-id="'+skill.id+'" data-tree="'+treeId+'" data-tier="'+skill.tier+'"></div>');
                    if(skill.icon){
                        skillNode.append('<div class="rpg-skill-icon"><img src="'+skill.icon+'" alt="" /></div>');
                    }
                    skillNode.append('<div class="rpg-skill-name">'+skill.name+'</div>');
                    skillNode.append('<div class="rpg-skill-tooltip">'+skill.tooltip+'</div>');
                    skillNode.append('<div class="rpg-skill-cost">'+skill.cost+' pt</div>');
                    if(skill.prereqs && skill.prereqs.length){
                        skillNode.append('<div class="rpg-skill-prereqs" data-prereqs="'+skill.prereqs.join(',')+'">'+data.i18n.requiresSkills+skill.prereqs.map(id=>getSkillName(id)).join(', ')+'</div>');
                    }
                    const row = layout.rows[skill.id] || 0;
                    const top = paddingOffset + (row * layout.rowHeight);
                    skillNode.css('top', top + 'px');
                    skillNode.on('click', ()=>toggleSkill(skill));
                    tierCol.append(skillNode);
                });
                tierColumn.append(tierCol);
                tiersWrap.append(tierColumn);
            }
            treeWrap.append(tiersWrap);
            body.append(treeWrap);
        });
        renderPointSummary();
        drawLines();
        updateSkillStates();
    }

    // Determine rows so connected skills align horizontally. If multiple prerequisites exist
    // the dependent skill takes the row of the first prerequisite, and subsequent prerequisites
    // are pushed to rows beneath that primary requirement.
    function calculateLayoutForTree(treeId){
        const skills = data.skills.filter(s=>s.tree===treeId);
        const rows = {};
        let nextRow = 0;
        const rowHeight = 120;
        const tierSkills = tier=>skills.filter(s=>parseInt(s.tier,10)===tier).sort((a,b)=>a.name.localeCompare(b.name));
        const usedRows = new Set();

        // seed tier 1
        tierSkills(1).forEach(skill=>{
            while(usedRows.has(nextRow)) nextRow++;
            rows[skill.id] = nextRow;
            usedRows.add(nextRow);
            nextRow++;
        });

        for(let tier=2;tier<=4;tier++){
            const sharedPrereqRows = {};
            tierSkills(tier).forEach(skill=>{
                if(skill.prereqs && skill.prereqs.length){
                    const primary = skill.prereqs[0];
                    const primaryRow = rows[primary] !== undefined ? rows[primary] : (rows[primary] = nextRow++);
                    usedRows.add(primaryRow);
                    const offset = sharedPrereqRows[primaryRow] || 0;
                    let targetRow = primaryRow + offset;
                    while(usedRows.has(targetRow)){
                        targetRow++;
                    }
                    rows[skill.id] = targetRow;
                    sharedPrereqRows[primaryRow] = (targetRow - primaryRow) + 1;
                    usedRows.add(targetRow);
                    nextRow = Math.max(nextRow, targetRow + 1);
                    skill.prereqs.slice(1).forEach(pr=>{
                        if(rows[pr] === undefined){
                            while(usedRows.has(nextRow)) nextRow++;
                            rows[pr] = nextRow;
                            usedRows.add(nextRow);
                            nextRow++;
                        }
                    });
                } else {
                    while(usedRows.has(nextRow)) nextRow++;
                    rows[skill.id] = nextRow;
                    usedRows.add(nextRow);
                    nextRow++;
                }
            });
        }

        // ensure all skills have a row
        skills.forEach(skill=>{
            if(rows[skill.id] === undefined){
                while(usedRows.has(nextRow)) nextRow++;
                rows[skill.id] = nextRow;
                usedRows.add(nextRow);
                nextRow++;
            }
        });

        return { rows, totalRows: Math.max(1, nextRow), rowHeight };
    }

    function dataLabel(base){
        return base;
    }

    function toggleSkill(skill){
        const id = skill.instance.toString();
        const isSelected = !!selectedSkills[id];

        if(isSelected){
            const dependants = getSelectedDependants(skill);
            if(dependants.length){
                const dependantNames = dependants.map(dep=>getSkillName(dep.id)).join(', ');
                showMessage(`Schopnost ${skill.name} nelze odebrat, jelikož je předpokladem pro ${dependantNames}`);
                return;
            }
            if(!maintainsTierRequirementsAfterRemoval(skill)){
                showMessage(data.i18n.tierRemovalBlocked);
                return;
            }
            delete selectedSkills[id];
        } else {
            if(!canSelectSkill(skill)){
                showMessage(validateSkill(skill));
                return;
            }
            selectedSkills[id] = true;
        }
        renderPointSummary();
        updateSkillStates();
        drawLines();
    }

    function maintainsTierRequirementsAfterRemoval(skill){
        const tree = data.trees.find(t=>t.id===skill.tree);
        if(!tree) return true;
        const reqs = tree.tier_requirements || {};
        const excluded = skill.instance.toString();
        const spent = {1:0,2:0,3:0,4:0};

        Object.keys(selectedSkills).forEach(id=>{
            if(id === excluded) return;
            const s = findSkillByInstance(id);
            if(s && s.tree === skill.tree){
                spent[s.tier] = (spent[s.tier]||0) + parseFloat(s.cost||0);
            }
        });

        return data.skills
            .filter(s=>s.tree === skill.tree && selectedSkills[s.instance] && s.instance.toString() !== excluded)
            .every(s=>{
                if(s.tier <= 1) return true;
                const requiredPoints = parseFloat(reqs[s.tier-1] || 0);
                if(requiredPoints <= 0) return true;
                return spent[s.tier-1] >= requiredPoints;
            });
    }

    function getSelectedDependants(skill){
        return data.skills
            .filter(s=>s.tree === skill.tree)
            .filter(s=>Array.isArray(s.prereqs) && s.prereqs.includes(skill.id))
            .filter(s=>selectedSkills[s.instance]);
    }

    function calculatePoints(){
        const totals = {1: data.settings.tier_points[1] || 0, 2: data.settings.tier_points[2] || 0, 3: data.settings.tier_points[3] || 0, 4: data.settings.tier_points[4] || 0};
        const spent = {1:0,2:0,3:0,4:0};
        Object.keys(selectedSkills).forEach(id=>{
            const skill = findSkillByInstance(id);
            if(skill){
                spent[skill.tier] = (spent[skill.tier]||0) + parseFloat(skill.cost||0);
            }
        });
        return {totals, spent};
    }

    function convertPoints(amount, fromTier, toTier){
        if(fromTier === toTier) return amount;
        const rule = (data.settings.conversions||[]).find(r=>parseInt(r.from,10)===parseInt(fromTier,10) && parseInt(r.to,10)===parseInt(toTier,10));
        if(!rule) return 0;
        return amount * parseFloat(rule.ratio||0);
    }

    function hasPointsForSkill(skill){
        const {totals, spent} = calculatePoints();
        const available = totals[skill.tier] - spent[skill.tier];
        if(available >= skill.cost) return true;
        // attempt conversions from other tiers
        let effective = available;
        for(let t=1;t<=4;t++){
            if(t===skill.tier) continue;
            const diff = totals[t] - spent[t];
            if(diff>0){
                effective += convertPoints(diff, t, skill.tier);
            }
        }
        return effective >= skill.cost;
    }

    function tierRequirementMet(skill){
        const tree = data.trees.find(t=>t.id===skill.tree);
        if(!tree) return true;
        const reqs = tree.tier_requirements || {};
        if(skill.tier<=1) return true;
        const requiredPoints = parseFloat(reqs[skill.tier-1] || 0);
        if(requiredPoints<=0) return true;
        const spent = {1:0,2:0,3:0,4:0};
        Object.keys(selectedSkills).forEach(id=>{
            const s = findSkillByInstance(id);
            if(s && s.tree===skill.tree){
                spent[s.tier] = (spent[s.tier]||0) + parseFloat(s.cost||0);
            }
        });
        return spent[skill.tier-1] >= requiredPoints;
    }

    function prerequisitesMet(skill){
        if(!skill.prereqs || !skill.prereqs.length) return true;
        return skill.prereqs.every(id=>{
            const instanceId = getInstanceIdForSkill(id, skill.tree);
            return instanceId ? selectedSkills[instanceId] : false;
        });
    }

    function validateSkill(skill){
        if(!selectedTrees.includes(skill.tree)) return data.i18n.treeRequired;
        if(!tierRequirementMet(skill)) return data.i18n.lockedByTier;
        if(!prerequisitesMet(skill)) return data.i18n.requiresSkills + skill.prereqs.map(getSkillName).join(', ');
        if(!hasPointsForSkill(skill)) return data.i18n.insufficientPoints;
        return '';
    }

    function canSelectSkill(skill){
        const message = validateSkill(skill);
        return message === '';
    }

    function updateSkillStates(){
        $('.rpg-skill').each(function(){
            const instanceId = $(this).data('instance');
            const skill = findSkillByInstance(instanceId);
            if(!skill) return;
            const isSelected = !!selectedSkills[instanceId];
            $(this).toggleClass('rpg-selected', isSelected);
            const msg = validateSkill(skill);
            if(msg!=='' && !isSelected){
                $(this).addClass('rpg-locked').attr('title', msg);
            } else {
                $(this).removeClass('rpg-locked').attr('title','');
            }
        });
    }

    function renderPointSummary(){
        const summary = $('#rpg-point-summary');
        summary.empty();
        const {totals, spent} = calculatePoints();
        const list = $('<ul class="rpg-point-list"></ul>');
        for(let t=1;t<=4;t++){
            list.append('<li>Tier '+t+': '+spent[t]+'/'+totals[t]+'</li>');
        }
        summary.append('<h3>Points</h3>');
        summary.append(list);
    }

    function showMessage(msg){
        if(!msg) return;
        const container = $('#rpg-builder-messages');
        container.text(msg).show();
        setTimeout(()=>container.fadeOut(), 2500);
    }

    function getSkillName(id){
        const skill = data.skills.find(s=>s.id===parseInt(id,10));
        return skill ? skill.name : '';
    }

    function bindActions(){
        $('.rpg-save-build').on('click', function(){
            if(data.settings.require_login && !data.currentUser){
                showMessage(data.i18n.loginRequired);
                return;
            }
            $.post(data.ajaxUrl, {action:'rpg_skill_trees_save_build', nonce:data.nonce, build: JSON.stringify({trees:selectedTrees, skills:selectedSkills})}, function(resp){
                if(resp.success){
                    showMessage(data.i18n.saved);
                }
            });
        });
        $('.rpg-load-build').on('click', function(){
            $.post(data.ajaxUrl, {action:'rpg_skill_trees_load_build', nonce:data.nonce}, function(resp){
                if(resp.success && resp.data){
                    selectedTrees = resp.data.trees || [];
                    selectedSkills = normalizeLoadedSkills(resp.data.skills || {});
                    $('#rpg-tree-list input').prop('checked', false);
                    selectedTrees.forEach(id=>{ $('#rpg-tree-list input[value="'+id+'"]').prop('checked', true); });
                    renderBuilder();
                }
            });
        });
        $('.rpg-reset-build').on('click', function(){
            selectedSkills = {};
            renderBuilder();
        });
    }

    function drawLines(){
        if(!svg) return;
        while (svg.firstChild) svg.removeChild(svg.firstChild);
        const body = document.getElementById('rpg-builder-body');
        const rect = body.getBoundingClientRect();
        svg.setAttribute('width', rect.width);
        svg.setAttribute('height', rect.height);
        svg.style.top = `${body.offsetTop}px`;
        svg.style.left = `${body.offsetLeft}px`;
        $('.rpg-skill').each(function(){
            const instanceId = $(this).data('instance');
            const skill = findSkillByInstance(instanceId);
            if(!skill || !skill.prereqs) return;
            const targetRect = this.getBoundingClientRect();
            skill.prereqs.forEach(pr=>{
                const prereqInstance = getInstanceIdForSkill(pr, skill.tree);
                if(!prereqInstance) return;
                const prereqEl = $('.rpg-skill[data-instance="'+prereqInstance+'"]')[0];
                if(!prereqEl) return;
                const prereqRect = prereqEl.getBoundingClientRect();
                const start = {
                    x: prereqRect.left - rect.left + prereqRect.width,
                    y: prereqRect.top - rect.top + prereqRect.height/2
                };
                const end = {
                    x: targetRect.left - rect.left,
                    y: targetRect.top - rect.top + targetRect.height/2
                };
                const midX = (start.x + end.x) / 2;
                const points = [
                    `${start.x},${start.y}`,
                    `${midX},${start.y}`,
                    `${midX},${end.y}`,
                    `${end.x},${end.y}`
                ].join(' ');
                const poly = document.createElementNS('http://www.w3.org/2000/svg','polyline');
                poly.setAttribute('points', points);
                poly.setAttribute('fill','none');
                poly.setAttribute('stroke', '#8ab4f8');
                poly.setAttribute('stroke-width', '2');
                poly.setAttribute('stroke-linejoin','round');
                svg.appendChild(poly);
            });
        });
    }

    $(document).ready(function(){
        init();
        $(window).on('resize', drawLines);
    });
})(jQuery);
