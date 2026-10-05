import test from 'node:test';
import assert from 'node:assert/strict';
import { weekFromHours, hoursFromWeek, copyWeekdayHours } from '../../resources/js/Support/businessSetup.js';
test('round trips closed days and split hours without losing sequence',()=>{
 const hours=[{day_of_week:2,sequence:2,opens_at:'13:00:00',closes_at:'18:00:00'},{day_of_week:2,sequence:1,opens_at:'09:00:00',closes_at:'12:00:00'}];
 const week=weekFromHours(hours);assert.equal(week[0].open,false);assert.equal(week[1].periods.length,2);
 assert.deepEqual(hoursFromWeek(week),[{day_of_week:2,sequence:1,opens_at:'09:00',closes_at:'12:00'},{day_of_week:2,sequence:2,opens_at:'13:00',closes_at:'18:00'}]);
});
test('copy weekdays keeps weekend and independently editable periods',()=>{
 const week=weekFromHours([{day_of_week:1,sequence:1,opens_at:'10:00',closes_at:'17:00'},{day_of_week:6,sequence:1,opens_at:'11:00',closes_at:'15:00'}]);
 const copied=copyWeekdayHours(week,1);copied[1].periods[0].opens_at='09:00';
 assert.equal(copied[0].periods[0].opens_at,'10:00');assert.equal(week[1].open,false);assert.equal(copied[5].periods[0].opens_at,'11:00');assert.equal(copied[6].open,false);
});
