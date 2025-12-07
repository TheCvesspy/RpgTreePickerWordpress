(function($){
    const data = window.RPGSkillTreesData || {};
    let selectedTrees = [];
    let selectedSkills = {};
    let svg;

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
                for (const key in selectedSkills){
                    if(selectedSkills[key] === true){
                        const skill = data.skills.find(s=>s.id===parseInt(key,10));
                        if(skill && skill.tree === val){
                            delete selectedSkills[key];
                        }
                    }
                }
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
            for(let tier=1;tier<=4;tier++){
                const tierCol = $('<div class="rpg-tier" data-tier="'+tier+'"><h4>'+dataLabel('Tier')+' '+tier+'</h4></div>');
                (grouped[treeId] && grouped[treeId][tier] ? grouped[treeId][tier] : []).forEach(skill=>{
                    const skillNode = $('<div class="rpg-skill" data-id="'+skill.id+'" data-tree="'+treeId+'" data-tier="'+skill.tier+'"></div>');
                    if(skill.icon){
                        skillNode.append('<div class="rpg-skill-icon"><img src="'+skill.icon+'" alt="" /></div>');
                    }
                    skillNode.append('<div class="rpg-skill-name">'+skill.name+'</div>');
                    skillNode.append('<div class="rpg-skill-tooltip">'+skill.tooltip+'</div>');
                    skillNode.append('<div class="rpg-skill-cost">'+skill.cost+' pt</div>');
                    if(skill.prereqs && skill.prereqs.length){
                        skillNode.append('<div class="rpg-skill-prereqs" data-prereqs="'+skill.prereqs.join(',')+'">'+data.i18n.requiresSkills+skill.prereqs.map(id=>getSkillName(id)).join(', ')+'</div>');
                    }
                    skillNode.on('click', ()=>toggleSkill(skill));
                    tierCol.append(skillNode);
                });
                tiersWrap.append(tierCol);
            }
            treeWrap.append(tiersWrap);
            body.append(treeWrap);
        });
        renderPointSummary();
        drawLines();
        updateSkillStates();
    }

    function dataLabel(base){
        return base;
    }

    function toggleSkill(skill){
        if(!canSelectSkill(skill)){
            showMessage(validateSkill(skill));
            return;
        }
        const id = skill.id.toString();
        if(selectedSkills[id]){
            delete selectedSkills[id];
        } else {
            selectedSkills[id] = true;
        }
        renderPointSummary();
        updateSkillStates();
        drawLines();
    }

    function calculatePoints(){
        const totals = {1: data.settings.tier_points[1] || 0, 2: data.settings.tier_points[2] || 0, 3: data.settings.tier_points[3] || 0, 4: data.settings.tier_points[4] || 0};
        const spent = {1:0,2:0,3:0,4:0};
        Object.keys(selectedSkills).forEach(id=>{
            const skill = data.skills.find(s=>s.id===parseInt(id,10));
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
            const s = data.skills.find(x=>x.id===parseInt(id,10));
            if(s && s.tree===skill.tree){
                spent[s.tier] = (spent[s.tier]||0) + parseFloat(s.cost||0);
            }
        });
        return spent[skill.tier-1] >= requiredPoints;
    }

    function prerequisitesMet(skill){
        if(!skill.prereqs || !skill.prereqs.length) return true;
        return skill.prereqs.every(id=>selectedSkills[id]);
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
            const id = parseInt($(this).data('id'),10);
            const skill = data.skills.find(s=>s.id===id);
            if(!skill) return;
            const isSelected = !!selectedSkills[id];
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
                    selectedSkills = resp.data.skills || {};
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
        const container = document.getElementById('rpg-builder-body');
        const rect = container.getBoundingClientRect();
        svg.setAttribute('width', rect.width);
        svg.setAttribute('height', rect.height);
        $('.rpg-skill').each(function(){
            const id = parseInt($(this).data('id'),10);
            const skill = data.skills.find(s=>s.id===id);
            if(!skill || !skill.prereqs) return;
            const targetRect = this.getBoundingClientRect();
            skill.prereqs.forEach(pr=>{
                const prereqEl = $('.rpg-skill[data-id="'+pr+'"]')[0];
                if(!prereqEl) return;
                const prereqRect = prereqEl.getBoundingClientRect();
                const line = document.createElementNS('http://www.w3.org/2000/svg','line');
                line.setAttribute('x1', prereqRect.left - rect.left + prereqRect.width/2);
                line.setAttribute('y1', prereqRect.top - rect.top + prereqRect.height/2);
                line.setAttribute('x2', targetRect.left - rect.left + targetRect.width/2);
                line.setAttribute('y2', targetRect.top - rect.top + targetRect.height/2);
                line.setAttribute('stroke', '#8ab4f8');
                line.setAttribute('stroke-width', '2');
                svg.appendChild(line);
            });
        });
    }

    $(document).ready(init);
})(jQuery);
