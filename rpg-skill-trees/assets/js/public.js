(function($){
    const data = window.RPGSkillTreesData || {};
    let selectedTrees = [];
    let selectedSkills = {};
    let userConversions = [];
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

    function buildSkillNode(skill){
        const skillNode = $('<div class="rpg-skill" data-instance="'+skill.instance+'" data-id="'+skill.id+'" data-tree="'+skill.tree+'" data-tier="'+skill.tier+'"></div>');
        if(skill.icon){
            skillNode.append('<div class="rpg-skill-icon"><img src="'+skill.icon+'" alt="" /></div>');
        }
        skillNode.append('<div class="rpg-skill-name">'+skill.name+'</div>');
        skillNode.append('<div class="rpg-skill-tooltip">'+skill.tooltip+'</div>');
        if(skill.prereqs && skill.prereqs.length){
            skillNode.append('<div class="rpg-skill-prereqs" data-prereqs="'+skill.prereqs.join(',')+'">'+data.i18n.requiresSkills+skill.prereqs.map(id=>getSkillName(id)).join(', ')+'</div>');
        }
        return skillNode;
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
                    const skillNode = buildSkillNode(skill);
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

    // Determine rows so connected skills align horizontally while keeping a consistent vertical gap
    // between cards. Skills inherit the row of their primary prerequisite when possible; siblings
    // sharing a prerequisite are stacked underneath in alphabetical order.
    const rowHeightCache = {};

    function getSkillRowHeight(treeId){
        if(rowHeightCache[treeId]){
            return rowHeightCache[treeId];
        }

        const gap = 12; // ensures at least 10px spacing between card edges
        const skills = data.skills.filter(s=>s.tree===treeId);
        const probeTier = $('<div class="rpg-tier" style="position:absolute; visibility:hidden; width:220px; padding:12px;"></div>');
        $('body').append(probeTier);

        let maxHeight = 0;
        skills.forEach(skill=>{
            const node = buildSkillNode(skill);
            node.css({position:'relative', left:'auto', right:'auto', top:'auto'});
            probeTier.append(node);
            maxHeight = Math.max(maxHeight, node.outerHeight(true));
            node.remove();
        });

        // If no skills exist, fall back to a minimal height so the UI remains stable
        if(maxHeight === 0){
            const fallback = $('<div class="rpg-skill" style="position:relative; left:auto; right:auto; top:auto;">&nbsp;</div>');
            probeTier.append(fallback);
            maxHeight = fallback.outerHeight(true);
            fallback.remove();
        }

        probeTier.remove();
        rowHeightCache[treeId] = maxHeight + gap;
        return rowHeightCache[treeId];
    }

    function calculateLayoutForTree(treeId){
        const skills = data.skills.filter(s=>s.tree===treeId);
        const rows = {};
        const rowHeight = getSkillRowHeight(treeId);
        let nextRow = 0;
        const rowOwners = {};
        const tierSkills = tier=>skills.filter(s=>parseInt(s.tier,10)===tier);
        const getName = id => {
            const skill = skills.find(s=>s.id===id);
            return skill ? skill.name : '';
        };

        const findAvailableRow = (startRow, ownerId) => {
            let row = startRow;
            while(rowOwners[row] !== undefined && rowOwners[row] !== ownerId){
                row++;
            }
            return row;
        };

        const assignRow = (skill, desiredRow, ownerId) => {
            const row = findAvailableRow(desiredRow, ownerId);
            rows[skill.id] = row;
            if(rowOwners[row] === undefined){
                rowOwners[row] = ownerId;
            }
            nextRow = Math.max(nextRow, row + 1);
        };

        tierSkills(1)
            .sort((a,b)=>a.name.localeCompare(b.name))
            .forEach(skill=>assignRow(skill, nextRow, skill.id));

        for(let tier=2;tier<=4;tier++){
            const singlePrereqs = [];
            const multiPrereqs = [];
            const noPrereqs = [];

            tierSkills(tier).forEach(skill=>{
                if(skill.prereqs && skill.prereqs.length){
                    if(skill.prereqs.length > 1){
                        multiPrereqs.push(skill);
                    } else {
                        singlePrereqs.push(skill);
                    }
                } else {
                    noPrereqs.push(skill);
                }
            });

            singlePrereqs
                .sort((a,b)=>a.name.localeCompare(b.name))
                .forEach(skill=>{
                    const prereqId = skill.prereqs[0];
                    const prereqSkill = skills.find(s=>s.id===parseInt(prereqId,10));
                    if(prereqSkill && rows[prereqSkill.id] === undefined){
                        assignRow(prereqSkill, nextRow, prereqSkill.id);
                    }
                    const baseRow = rows[prereqId] !== undefined ? rows[prereqId] : nextRow;
                    const owner = rowOwners[baseRow] !== undefined ? rowOwners[baseRow] : prereqId;
                    assignRow(skill, baseRow, owner);
                });

            multiPrereqs
                .sort((a,b)=>a.name.localeCompare(b.name))
                .forEach(skill=>{
                    assignRow(skill, nextRow, skill.id);
                });

            noPrereqs
                .sort((a,b)=>a.name.localeCompare(b.name))
                .forEach(skill=>assignRow(skill, nextRow, skill.id));
        }

        skills.forEach(skill=>{
            if(rows[skill.id] === undefined){
                assignRow(skill, nextRow, skill.id);
            }
        });

        return { rows, totalRows: Math.max(1, nextRow), rowHeight };
    }

    function dataLabel(base){
        const map = {
            'Points': 'Body',
            'Tier': 'Úroveň',
        };
        return map[base] || base;
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
        const baseTotals = {1: parseFloat(data.settings.tier_points[1] || 0), 2: parseFloat(data.settings.tier_points[2] || 0), 3: parseFloat(data.settings.tier_points[3] || 0), 4: parseFloat(data.settings.tier_points[4] || 0)};
        const totals = {...baseTotals};
        userConversions.forEach(conv=>{
            totals[conv.from] = (totals[conv.from]||0) - conv.amount;
            totals[conv.to] = (totals[conv.to]||0) + conv.received;
        });
        const spent = {1:0,2:0,3:0,4:0};
        Object.keys(selectedSkills).forEach(id=>{
            const skill = findSkillByInstance(id);
            if(skill){
                spent[skill.tier] = (spent[skill.tier]||0) + parseFloat(skill.cost||0);
            }
        });
        return {baseTotals, totals, spent};
    }

    function findConversionRule(fromTier, toTier){
        return (data.settings.conversions||[]).find(r=>parseInt(r.from,10)===parseInt(fromTier,10) && parseInt(r.to,10)===parseInt(toTier,10));
    }

    function fractionFromRatio(ratio){
        const decimals = (ratio.toString().split('.')[1] || '').length;
        const denominator = Math.pow(10, decimals);
        const numerator = Math.round(ratio * denominator);
        const gcd = (a, b) => b ? gcd(b, a % b) : Math.abs(a);
        const divisor = gcd(numerator, denominator) || 1;
        return { numerator: numerator / divisor, denominator: denominator / divisor };
    }

    function getConversionStep(rule){
        const ratio = parseFloat(rule?.ratio || 0);
        if(!ratio || Number.isNaN(ratio)) return { amount: 0, received: 0 };
        if(Number.isInteger(ratio)) return { amount: 1, received: ratio };
        const { numerator, denominator } = fractionFromRatio(ratio);
        return { amount: denominator, received: numerator };
    }

    function hasPointsForSkill(skill){
        const {totals, spent} = calculatePoints();
        const available = totals[skill.tier] - spent[skill.tier];
        return available >= skill.cost;
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
        const {baseTotals, totals, spent} = calculatePoints();
        summary.append('<h3>'+dataLabel('Points')+'</h3>');

        const list = $('<div class="rpg-point-list"></div>');
        for(let t=1;t<=4;t++){
            const delta = totals[t] - baseTotals[t];
            const deltaText = delta !== 0 ? ' ('+(delta>0?'+':'')+delta+')' : '';
            const item = $('<div class="rpg-point-row" data-tier="'+t+'"></div>');
            const label = $('<div class="rpg-point-label">'+dataLabel('Tier')+' '+t+'</div>');
            const values = $('<div class="rpg-point-values">'+spent[t]+'/'+totals[t]+deltaText+'</div>');
            item.append(label).append(values);

            const controls = $('<div class="rpg-point-actions"></div>');
            const upRule = t > 1 ? findConversionRule(t, t-1) : null;
            if(upRule){
                const upBtn = $('<button type="button" class="rpg-convert-btn" title="'+dataLabel('Tier')+' '+t+' → '+dataLabel('Tier')+' '+(t-1)+'">↑</button>');
                upBtn.on('click', ()=>applyQuickConversion(t, t-1));
                controls.append(upBtn);
            }
            const downRule = t < 4 ? findConversionRule(t, t+1) : null;
            if(downRule){
                const downBtn = $('<button type="button" class="rpg-convert-btn" title="'+dataLabel('Tier')+' '+t+' → '+dataLabel('Tier')+' '+(t+1)+'">↓</button>');
                downBtn.on('click', ()=>applyQuickConversion(t, t+1));
                controls.append(downBtn);
            }
            if(controls.children().length){
                item.append(controls);
            }
            list.append(item);
        }
        summary.append(list);
        renderConversionHistory(summary);
    }

    function applyQuickConversion(fromTier, toTier){
        if(Math.abs(fromTier - toTier) !== 1){
            showMessage('Conversions are only allowed between neighboring tiers.');
            return;
        }
        const rule = findConversionRule(fromTier, toTier);
        if(!rule){
            showMessage('Conversion not allowed for those tiers.');
            return;
        }
        const { amount, received } = getConversionStep(rule);
        if(!amount || !received){
            showMessage('Conversion not allowed for those tiers.');
            return;
        }
        const {totals, spent} = calculatePoints();
        const available = (totals[fromTier]||0) - (spent[fromTier]||0);
        if(amount > available){
            showMessage('Not enough points to convert from Tier '+fromTier+'.');
            return;
        }
        userConversions.push({from: fromTier, to: toTier, amount, received});
        renderPointSummary();
        updateSkillStates();
    }

    function renderConversionHistory(summary){
        const history = $('<div class="rpg-conversion-history"></div>');
        if(userConversions.length){
            userConversions.forEach((conv, idx)=>{
                const item = $('<div class="rpg-conversion-entry"></div>');
                item.append('<span>'+dataLabel('Tier')+' '+conv.from+' -'+conv.amount+' → '+dataLabel('Tier')+' '+conv.to+' +'+conv.received+'</span>');
                const undoBtn = $('<button type="button" class="button button-small">Undo</button>');
                undoBtn.on('click', function(){
                    userConversions.splice(idx,1);
                    renderPointSummary();
                    updateSkillStates();
                });
                item.append(undoBtn);
                history.append(item);
            });
        }
        summary.append(history);
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
        $('.rpg-reset-build').on('click', function(){
            selectedSkills = {};
            userConversions = [];
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
