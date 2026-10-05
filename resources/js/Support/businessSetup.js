export function weekFromHours(hours) {
    return Array.from({length:7},(_,index)=>{
        const periods=hours.filter(h=>Number(h.day_of_week)===index+1).sort((a,b)=>a.sequence-b.sequence).map(h=>({opens_at:h.opens_at.slice(0,5),closes_at:h.closes_at.slice(0,5)}));
        return {day_of_week:index+1,open:periods.length>0,periods};
    });
}
export function hoursFromWeek(week) {
    return week.flatMap(day=>day.open?day.periods.map((period,index)=>({day_of_week:day.day_of_week,opens_at:period.opens_at,closes_at:period.closes_at,sequence:index+1})):[]);
}
export function copyWeekdayHours(week,sourceDay) {
    const source=week.find(day=>day.day_of_week===sourceDay);
    return week.map(day=>day.day_of_week<=5?{...day,open:source.open,periods:source.periods.map(p=>({...p}))}:{...day,periods:day.periods.map(p=>({...p}))});
}
