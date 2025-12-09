(function($){
    const data = window.RPGSkillTreesData || {};
    let selectedTrees = [];
    let selectedSkills = {};
    let userConversions = [];
    let showRules = false;
    let hoverTooltip;
    let svg;
    let html2CanvasPromise;
    let messageHideTimer;

    function sortSkills(a,b){
        const orderA = parseInt(a && a.sort_order !== undefined ? a.sort_order : 0, 10) || 0;
        const orderB = parseInt(b && b.sort_order !== undefined ? b.sort_order : 0, 10) || 0;
        if(orderA !== orderB){
            return orderA - orderB;
        }
        const nameA = (a && a.name) ? a.name : '';
        const nameB = (b && b.name) ? b.name : '';
        return nameA.localeCompare(nameB);
    }

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
        showRules = $('#rpg-toggle-rules').is(':checked');
        renderPointSummary();
        bindActions();
    }

    function renderTreeSelector(){
        const list = $('#rpg-tree-list');
        list.empty();
        const selectAllId = 'rpg-tree-select-all';
        const selectAll = $('<label class="rpg-tree-option rpg-tree-select-all"><input type="checkbox" id="'+selectAllId+'"> Vyber vše</label>');
        list.append(selectAll);
        selectAll.find('input').on('change', function(){
            const checked = $(this).is(':checked');
            selectedTrees = checked ? data.trees.map(t=>t.id) : [];
            list.find('input.rpg-tree-toggle').prop('checked', checked);
            if(!checked){
                selectedSkills = {};
            }
            renderBuilder();
        });
        data.trees.forEach(tree => {
            const id = 'rpg-tree-'+tree.id;
            const checkbox = $('<label class="rpg-tree-option"><input type="checkbox" value="'+tree.id+'" id="'+id+'" class="rpg-tree-toggle"> '+tree.name+'</label>');
            list.append(checkbox);
        });
        list.find('input.rpg-tree-toggle').on('change', function(){
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
            syncSelectAllToggle(list);
            renderBuilder();
        });
        syncSelectAllToggle(list);
    }

    function syncSelectAllToggle(list){
        const toggles = list.find('input.rpg-tree-toggle');
        const allChecked = toggles.length > 0 && toggles.filter(':checked').length === toggles.length;
        list.find('#rpg-tree-select-all').prop('checked', allChecked);
    }

    function groupSkillsByTree(){
        const grouped = {};
        const sortedSkills = [...data.skills].sort(sortSkills);
        sortedSkills.forEach(skill=>{
            if(!grouped[skill.tree]) grouped[skill.tree] = {};
            if(!grouped[skill.tree][skill.tier]) grouped[skill.tree][skill.tier] = [];
            grouped[skill.tree][skill.tier].push(skill);
        });
        return grouped;
    }

    function buildTooltipContent(skill){
        const parts = [];
        if(skill.tooltip){
            parts.push('<div class="rpg-tooltip-line">'+skill.tooltip+'</div>');
        }
        if(skill.effect){
            parts.push('<div class="rpg-tooltip-effect">'+skill.effect+'</div>');
        }
        return parts.join('');
    }

    function buildSkillNode(skill, highestTier){
        const skillNode = $('<div class="rpg-skill" data-instance="'+skill.instance+'" data-id="'+skill.id+'" data-tree="'+skill.tree+'" data-tier="'+skill.tier+'"></div>');
        const tooltipContent = buildTooltipContent(skill);
        skillNode.data('tooltip', tooltipContent);
        const maxTier = parseInt(highestTier, 10) || 0;
        if(maxTier > 0 && parseInt(skill.tier,10) === maxTier){
            skillNode.addClass('rpg-skill--highest-tier');
        }
        if(skill.icon){
            skillNode.append('<div class="rpg-skill-icon"><img src="'+skill.icon+'" alt="" /></div>');
        }
        skillNode.append('<div class="rpg-skill-name">'+skill.name+'</div>');
        if(showRules && tooltipContent){
            skillNode.append('<div class="rpg-skill-tooltip">'+tooltipContent+'</div>');
        }
        if(skill.prereqs && skill.prereqs.length){
            skillNode.append('<div class="rpg-skill-prereqs" data-prereqs="'+skill.prereqs.join(',')+'">'+data.i18n.requiresSkills+skill.prereqs.map(id=>getSkillName(id)).join(', ')+'</div>');
        }
        return skillNode;
    }

    function ensureHoverTooltip(){
        if(!hoverTooltip){
            hoverTooltip = $('<div id="rpg-hover-tooltip" class="rpg-hover-tooltip" role="tooltip" aria-hidden="true"></div>');
            $('body').append(hoverTooltip);
        }
        return hoverTooltip;
    }

    function showHoverTooltip(content, event){
        if(!content) return;
        const tooltip = ensureHoverTooltip();
        tooltip.html(content).show();
        positionHoverTooltip(event);
    }

    function positionHoverTooltip(event){
        if(!hoverTooltip || !hoverTooltip.is(':visible')) return;
        const offset = 12;
        hoverTooltip.css({ left: event.pageX + offset, top: event.pageY + offset });
    }

    function hideHoverTooltip(){
        if(hoverTooltip){
            hoverTooltip.hide().empty();
        }
    }

    const highestTierCache = {};

    function getHighestTierForTree(treeId){
        if(highestTierCache[treeId] !== undefined){
            return highestTierCache[treeId];
        }
        const tiers = data.skills.filter(s=>s.tree===treeId).map(s=>parseInt(s.tier,10) || 0);
        highestTierCache[treeId] = tiers.length ? Math.max(...tiers) : 0;
        return highestTierCache[treeId];
    }

    function renderBuilder(){
        hideHoverTooltip();
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
            const highestTier = getHighestTierForTree(treeId);
            for(let tier=1;tier<=4;tier++){
                const tierColumn = $('<div class="rpg-tier-column" data-tier="'+tier+'"></div>');
                tierColumn.append('<div class="rpg-tier-title">'+dataLabel('Tier')+' '+tier+'</div>');
                const tierCol = $('<div class="rpg-tier" data-tier="'+tier+'"></div>');
                const skills = (grouped[treeId] && grouped[treeId][tier] ? grouped[treeId][tier] : []);
                const paddingOffset = 0;
                const paddingBottom = 0;
                tierCol.css('min-height', (layout.totalRows * layout.rowHeight + paddingOffset + paddingBottom)+'px');
                tierCol.css('padding-top', paddingOffset+'px');
                tierCol.css('padding-bottom', paddingBottom+'px');
                skills.forEach(skill=>{
                    const skillNode = buildSkillNode(skill, highestTier);
                    const row = layout.rows[skill.id] || 0;
                    const top = paddingOffset + (row * layout.rowHeight);
                    skillNode.css('top', top + 'px');
                    if(skillNode.data('tooltip')){
                        skillNode.on('mouseenter', event => showHoverTooltip(skillNode.data('tooltip'), event));
                        skillNode.on('mousemove', positionHoverTooltip);
                        skillNode.on('mouseleave', hideHoverTooltip);
                    }
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

    function clearRowHeightCache(){
        Object.keys(rowHeightCache).forEach(key=> delete rowHeightCache[key]);
    }

    function getSkillRowHeight(treeId){
        if(rowHeightCache[treeId]){
            return rowHeightCache[treeId];
        }

        const gap = 0; // allow rows to sit directly against the card height without extra spacing
        const skills = data.skills.filter(s=>s.tree===treeId);
        const probeTier = $('<div class="rpg-tier" style="position:absolute; visibility:hidden; width:270px; padding:0;"></div>');
        $('body').append(probeTier);

        let maxHeight = 0;
        const highestTier = getHighestTierForTree(treeId);
        skills.forEach(skill=>{
            const node = buildSkillNode(skill, highestTier);
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
        rowHeightCache[treeId] = Math.ceil(maxHeight + gap);
        return rowHeightCache[treeId];
    }

    function calculateLayoutForTree(treeId){
        const skills = data.skills.filter(s=>s.tree===treeId);
        const rows = {};
        const rowHeight = getSkillRowHeight(treeId);
        let nextRow = 0;
        let rowOwners = {};
        const preferredRows = {};
        const tierSkills = tier=>skills.filter(s=>parseInt(s.tier,10)===tier);
        const getName = id => {
            const skill = skills.find(s=>s.id===id);
            return skill ? skill.name : '';
        };

        const recomputeNextRow = () => {
            const usedRows = Object.values(rows);
            nextRow = usedRows.length ? Math.max(...usedRows) + 1 : nextRow;
        };

        const findAvailableRow = (startRow, ownerId) => {
            let row = startRow;
            while(rowOwners[row] !== undefined && rowOwners[row] !== ownerId){
                row++;
            }
            return row;
        };

        const shiftRows = (startRow, delta) => {
            if(delta === 0) return;

            Object.keys(rows).forEach(id => {
                const currentRow = rows[id];
                if(currentRow >= startRow){
                    rows[id] = currentRow + delta;
                }
            });

            const newRowOwners = {};
            Object.keys(rowOwners)
                .map(r => parseInt(r,10))
                .sort((a,b)=>a-b)
                .forEach(row => {
                    const owner = rowOwners[row];
                    const target = row >= startRow ? row + delta : row;
                    newRowOwners[target] = owner;
                });

            rowOwners = newRowOwners;
            recomputeNextRow();
        };

        const ensureRowAvailable = (row, ownerId) => {
            if(rowOwners[row] !== undefined && rowOwners[row] !== ownerId){
                shiftRows(row, 1);
            }
        };

        const assignRow = (skill, desiredRow, ownerId) => {
            const row = findAvailableRow(desiredRow, ownerId);
            rows[skill.id] = row;
            if(rowOwners[row] === undefined){
                rowOwners[row] = ownerId;
            }
            nextRow = Math.max(nextRow, row + 1);
        };

        const setPreferredRowsForTier = orderedTierSkills => {
            const tierStart = nextRow;
            orderedTierSkills.forEach((skill, index)=>{
                preferredRows[skill.id] = tierStart + index;
            });
        };

        const tierOneSkills = tierSkills(1).sort(sortSkills);
        setPreferredRowsForTier(tierOneSkills);
        tierOneSkills.forEach(skill=>assignRow(skill, preferredRows[skill.id], skill.id));

        for(let tier=2;tier<=4;tier++){
            const orderedTierSkills = tierSkills(tier).sort(sortSkills);
            const multiPrereqs = [];
            const noPrereqs = [];

            setPreferredRowsForTier(orderedTierSkills);

            orderedTierSkills.forEach(skill=>{
                if(skill.prereqs && skill.prereqs.length){
                    if(skill.prereqs.length === 1){
                        return;
                    }
                    multiPrereqs.push(skill);
                } else {
                    noPrereqs.push(skill);
                }
            });

            const processedPrereqs = new Set();

            orderedTierSkills.forEach(skill => {
                if(!skill.prereqs || skill.prereqs.length !== 1){
                    return;
                }

                const prereqId = skill.prereqs[0];
                if(processedPrereqs.has(prereqId)) return;
                processedPrereqs.add(prereqId);

                const prereqSkill = skills.find(s=>s.id===parseInt(prereqId,10));
                if(prereqSkill && rows[prereqSkill.id] === undefined){
                    const preferredPrereqRow = preferredRows[prereqSkill.id] !== undefined ? preferredRows[prereqSkill.id] : nextRow;
                    assignRow(prereqSkill, preferredPrereqRow, prereqSkill.id);
                }

                const baseRow = rows[prereqId] !== undefined ? rows[prereqId] : preferredRows[skill.id];
                const siblings = orderedTierSkills.filter(s=>s.prereqs && s.prereqs.length === 1 && s.prereqs[0] === prereqId).sort(sortSkills);
                const owner = rowOwners[baseRow] !== undefined ? rowOwners[baseRow] : prereqId;

                siblings.forEach((sibling, index)=>{
                    const targetRow = baseRow + index;
                    ensureRowAvailable(targetRow, owner);
                    assignRow(sibling, targetRow, owner);
                });
            });

            multiPrereqs.forEach(skill=>{
                const startRow = preferredRows[skill.id] !== undefined ? preferredRows[skill.id] : nextRow;
                assignRow(skill, startRow, skill.id);
            });

            noPrereqs.forEach(skill=>{
                const startRow = preferredRows[skill.id] !== undefined ? preferredRows[skill.id] : nextRow;
                assignRow(skill, startRow, skill.id);
            });
        }

        skills.forEach(skill=>{
            if(rows[skill.id] === undefined){
                const startRow = preferredRows[skill.id] !== undefined ? preferredRows[skill.id] : nextRow;
                assignRow(skill, startRow, skill.id);
            }
        });

        // Remap rows to remove any gaps so the layout stays as compact as possible.
        const usedRows = [...new Set(Object.values(rows).sort((a,b)=>a-b))];
        if(usedRows.length){
            const remap = {};
            usedRows.forEach((row, index)=>{ remap[row] = index; });
            Object.keys(rows).forEach(id => { rows[id] = remap[rows[id]]; });
            nextRow = usedRows.length;
        } else {
            nextRow = 1;
        }

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
            showMessage(data.i18n && data.i18n.conversionNotAllowed ? data.i18n.conversionNotAllowed : 'Konverze pro tyto úrovně není povolena.');
            return;
        }
        const {totals, spent} = calculatePoints();
        const available = (totals[fromTier]||0) - (spent[fromTier]||0);
        if(amount > available){
            const prefix = data.i18n && data.i18n.conversionInsufficient ? data.i18n.conversionInsufficient : 'Nedostatek bodů pro konverzi z úrovně ';
            showMessage(prefix + fromTier + '.');
            return;
        }
        userConversions.push({from: fromTier, to: toTier, amount, received});
        renderPointSummary();
        updateSkillStates();
    }

    function showMessage(msg){
        if(!msg) return;
        const container = $('#rpg-builder-messages');
        container.stop(true, true).text(msg).show();
        if(messageHideTimer){
            clearTimeout(messageHideTimer);
        }
        messageHideTimer = setTimeout(()=>container.fadeOut(), 10000);
    }

    function getSkillName(id){
        const skill = data.skills.find(s=>s.id===parseInt(id,10));
        return skill ? skill.name : '';
    }

    function loadHtml2Canvas(){
        if(window.html2canvas){
            return Promise.resolve(window.html2canvas);
        }
        if(html2CanvasPromise){
            return html2CanvasPromise;
        }
        html2CanvasPromise = new Promise((resolve, reject)=>{
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
            script.onload = ()=> resolve(window.html2canvas);
            script.onerror = ()=> reject(new Error('Načtení knihovny html2canvas se nezdařilo'));
            document.head.appendChild(script);
        });
        return html2CanvasPromise;
    }

    async function exportAsPng(){
        const container = document.querySelector('.rpg-skill-trees-builder');
        if(!container){
            return;
        }
        const button = $('.rpg-export-png');
        const originalText = button.text();
        button.prop('disabled', true).text('Exportuje...');
        try {
            const html2canvas = await loadHtml2Canvas();
            const canvas = await html2canvas(container, { backgroundColor: '#0b1021', useCORS: true, scale: 2 });
            const link = document.createElement('a');
            link.href = canvas.toDataURL('image/png');
            link.download = 'skill-tree.png';
            link.click();
        } catch(err){
            console.error(err);
            showMessage((data.i18n && data.i18n.exportError) ? data.i18n.exportError : 'Export se nezdařil.');
        } finally {
            button.prop('disabled', false).text(originalText);
        }
    }

    function bindActions(){
        $('.rpg-reset-build').on('click', function(){
            selectedSkills = {};
            userConversions = [];
            renderBuilder();
        });

        $('#rpg-toggle-rules').on('change', function(){
            showRules = $(this).is(':checked');
            clearRowHeightCache();
            renderBuilder();
        });

        $('.rpg-export-png').on('click', exportAsPng);
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
