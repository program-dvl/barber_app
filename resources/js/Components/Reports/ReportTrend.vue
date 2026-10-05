<script setup>
import {computed,onBeforeUnmount,onMounted,ref} from 'vue';
const props=defineProps({trend:Object,format:Function,comparisonLabel:String});
const emit=defineEmits(['select']);
const element=ref(null); const width=ref(700); const active=ref(null); let observer;
onMounted(()=>{observer=new ResizeObserver(entries=>width.value=Math.max(260,entries[0].contentRect.width));observer.observe(element.value);});
onBeforeUnmount(()=>observer?.disconnect());
const points=computed(()=>props.trend?.points || []);
const height=194; const left=64; const right=12; const top=16; const bottom=28;
const lower=computed(()=>Math.min(0,...points.value.flatMap(point=>[point.value,point.previous ?? 0])));
const upper=computed(()=>Math.max(1,...points.value.flatMap(point=>[point.value,point.previous ?? 0]))*1.08);
const y=value=>top+(upper.value-value)/(upper.value-lower.value)*(height-top-bottom);
const x=index=>points.value.length===1 ? (width.value+left-right)/2 : left+(width.value-left-right)*(index/Math.max(1,points.value.length-1));
const path=series=>{let contiguous=false;return points.value.map((point,index)=>{if(point[series]===null){contiguous=false;return '';}const command=contiguous?'L':'M';contiguous=true;return `${command}${x(index)},${y(point[series])}`;}).join(' ');};
const ticks=computed(()=>[0,1,2,3].map(index=>lower.value+(upper.value-lower.value)*index/3));
const labels=computed(()=>{const steps=width.value<420?3:6;return points.value.map((point,index)=>({point,index})).filter(({index})=>index===0 || index===points.value.length-1 || index%Math.max(1,Math.ceil(points.value.length/steps))===0);});
const compact=value=>Math.abs(value)>=10000000 ? props.format(value/1000000)+'m' : Math.abs(value)>=100000 ? props.format(value/1000)+'k' : props.format(value);
const tooltipStyle=computed(()=>({left:`${Math.min(Math.max(x(active.value ?? 0)-90,4),width.value-188)}px`,top:'8px'}));
</script>
<template>
    <div ref="element" class="ra-trend">
        <div class="ra-legend"><span><i class="ra-current-line"/>Selected period</span><span v-if="points.some(point=>point.previous!==null)"><i class="ra-previous-line"/>{{ comparisonLabel || 'Previous period' }}</span></div>
        <svg :viewBox="`0 0 ${width} ${height}`" class="ra-trend-svg" role="group" aria-label="Trend chart. Focus a point for exact values; activate it to inspect that date range." @mouseleave="active=null">
            <g v-for="(tick,index) in ticks" :key="index"><line :x1="left" :x2="width-right" :y1="y(tick)" :y2="y(tick)" class="ra-grid-line"/><text :x="left-10" :y="y(tick)+4" text-anchor="end" class="ra-axis">{{ compact(Math.round(tick)) }}</text></g>
            <path v-if="points.some(point=>point.previous!==null)" :d="path('previous')" class="ra-comparison-path"/>
            <path :d="path('value')" class="ra-value-path"/>
            <g v-for="(point,index) in points" :key="index" role="button" tabindex="0" :aria-label="`${point.label}: ${format(point.value)}${point.previous!==null ? ', comparison '+format(point.previous) : ''}. Inspect records.`" class="ra-point" @mouseenter="active=index" @focus="active=index" @blur="active=null" @click="emit('select',point)" @keydown.enter.prevent="emit('select',point)" @keydown.space.prevent="emit('select',point)">
                <circle :cx="x(index)" :cy="y(point.value)" r="14" fill="transparent"/><circle :cx="x(index)" :cy="y(point.value)" :r="active===index || points.length===1 ? 4 : 2.5" class="ra-dot"/>
                <title>{{ point.label }} · {{ format(point.value) }}</title>
            </g>
            <text v-for="({point,index}) in labels" :key="index" :x="x(index)" :y="height-5" :text-anchor="index===0 ? 'start' : index===points.length-1 ? 'end' : 'middle'" class="ra-axis">{{ point.label }}</text>
        </svg>
        <div v-if="active!==null && points[active]" class="ra-chart-tooltip" :style="tooltipStyle" aria-live="polite"><strong>{{ points[active].label }}</strong><span>Selected <b>{{ format(points[active].value) }}</b></span><span v-if="points[active].previous!==null">{{ points[active].previous_label || 'Comparison' }} <b>{{ format(points[active].previous) }}</b></span></div>
        <details class="ra-data-alternative"><summary>View chart values</summary><div class="ra-chart-data"><button v-for="(point,index) in points" :key="index" type="button" @click="emit('select',point)"><span>{{ point.label }}</span><b>{{ format(point.value) }}</b><span v-if="point.previous!==null">Comparison {{ format(point.previous) }}</span></button></div></details>
    </div>
</template>
