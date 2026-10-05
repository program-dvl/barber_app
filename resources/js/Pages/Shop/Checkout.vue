<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { BanknotesIcon, BuildingLibraryIcon, CheckCircleIcon, CreditCardIcon, DevicePhoneMobileIcon, ArrowPathIcon, ArrowRightIcon, CalendarDaysIcon, EllipsisHorizontalIcon, LinkIcon, LockClosedIcon, PencilSquareIcon, PlusIcon, ReceiptPercentIcon, ScissorsIcon, ShoppingBagIcon, TrashIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Product/PageHeader.vue';
import AppButton from '@/Components/Product/AppButton.vue';
import AppDialog from '@/Components/Product/AppDialog.vue';
import AppSelect from '@/Components/Product/AppSelect.vue';
import SearchField from '@/Components/Product/SearchField.vue';
import DataTable from '@/Components/Product/DataTable.vue';
import { checkoutMoney, checkoutInitials, checkoutTime, paymentLabel, parseMoney, moneyInput, tenderSummary, basketPayload, pendingStorageKey, readPending } from '@/Support/checkoutWorkspace';
import '../../../css/checkout.css';

const props = defineProps({ appointments:Array, sales:Array, selectedAppointment:String, selectedVisit:Object, selectedSale:String, readyCount:Number, locations:Array, permissions:Object, filters:Object, salesPagination:Object, staffFilters:Array });
const page = usePage();
const business = computed(() => page.props.tenant.public_id);
const userId = computed(() => page.props.auth?.user?.id || page.props.user?.id || 'session');
const section = ref(props.filters?.section || (props.permissions.checkout ? 'checkout' : 'sales'));
const selected = ref('');
const visit = ref(null);
const items = ref([]);
const tips = ref([]);
const reason = ref('');
const applyDeposit = ref(true);
const quote = ref(null);
const lastQuote=ref(null);
const sale = ref(null);
const loading = ref(false);
const calculating = ref(false);
const working = ref(false);
const error = ref('');
const quoteError = ref('');
const notice = ref('');
const readySearch=ref(props.filters?.ready_search || '');
let readySearchTimer;
const pending = ref(null);
const prepared = computed(() => !!sale.value);
const completed = computed(() => sale.value?.status === 'completed');
const currency = computed(() => sale.value?.currency_code || visit.value?.appointment.currency_code || page.props.tenant?.regional?.currency_code || 'INR');
const totals = computed(() => sale.value || quote.value || lastQuote.value);
const money = (amount, code = currency.value) => checkoutMoney(amount,code,page.props.tenant?.regional?.locale);
const busy = computed(() => loading.value || working.value || !!pending.value);
const eligibleStaff=computed(()=>visit.value?.staff.filter(person=>person.eligible_for_addition) || []);
const staffName = id => visit.value?.staff.find(person=>person.id===id)?.display_name || 'Not attributed';
const payload = computed(() => basketPayload(items.value,tips.value,reason.value,applyDeposit.value));
const href = (name, id) => route(name,id ? [business.value,id] : business.value);
const receiptHref = value => href('business.checkout.receipt',value.public_id);
const calendarHref = appointment => href('business.calendar')+`?appointment=${appointment.public_id}&location=${appointment.location_public_id}`;
const rebookHref = appointment => href('business.calendar')+`?create=1&rebook=${appointment.public_id}${appointment.client_public_id ? '&client='+appointment.client_public_id : ''}`;
const errorMessage = exception => Object.values(exception.response?.data?.errors || {}).flat()[0] || exception.response?.data?.message || 'Connection interrupted. Your changes are still here. Try again.';
let visitRequest; let previewRequest; let previewTimer; let catalogueRequest; let catalogueTimer; let visitVersion = 0; let quoteVersion=0;

const method = ref('cash');
const paymentAmount = ref('');
const cashTendered = ref('');
const paymentReference = ref('');
const payments = ref([]);
const received = ref(false);
watch([method,paymentAmount,paymentReference,cashTendered],()=>received.value=false);
const tabsElement=ref(null);
const focusCatalogResult=()=>document.querySelector('.co-catalog-result:not(:disabled)')?.focus();
const methods = [
    {id:'cash',label:'Cash',icon:BanknotesIcon},{id:'card',label:'External card',icon:CreditCardIcon},
    {id:'bank_transfer',label:'Bank transfer',icon:BuildingLibraryIcon},{id:'payment_link',label:'Payment link',icon:LinkIcon},
    {id:'upi',label:'UPI',icon:DevicePhoneMobileIcon},{id:'custom',label:'Other',icon:EllipsisHorizontalIcon},
];
// These are ledger recording methods from ADR-020; they never initiate a provider charge.
const visibleMethods = computed(()=>methods.filter(item=>item.id!=='upi' || page.props.tenant?.regional?.country_code==='IN' || currency.value==='INR'));
const staged = computed(()=>tenderSummary(payments.value,sale.value?.balance_minor || 0));
const parsedAmount = computed(()=>parseMoney(paymentAmount.value,currency.value));
const cashChange = computed(()=>method.value==='cash' && parseMoney(cashTendered.value,currency.value)!==null && parsedAmount.value!==null ? parseMoney(cashTendered.value,currency.value)-parsedAmount.value : null);
const paymentValid = computed(()=>parsedAmount.value>0 && parsedAmount.value<=staged.value.remaining && (method.value!=='cash' || !cashTendered.value || cashChange.value>=0));
const resetPayment = () => {payments.value=[];received.value=false;paymentAmount.value=moneyInput(sale.value?.balance_minor,currency.value);cashTendered.value='';paymentReference.value='';};
const storageKey = () => pendingStorageKey(userId.value,business.value,sale.value.public_id);
function loadPending() {try {pending.value=readPending(window.sessionStorage,storageKey());} catch {pending.value=null;}}

async function choose(appointment) {
    if (working.value || pending.value) return;
    // Changing visits must not silently discard an edited, unsaved basket.
    if (selected.value && !prepared.value && dirty.value && selected.value!==appointment.public_id) {
        switchTarget.value=appointment; switchDialog.value?.open(); return;
    }
    await loadVisit(appointment);
}
const baseline = ref('');
const dirty = computed(()=>!!baseline.value && JSON.stringify(payload.value)!==baseline.value);
async function loadVisit(appointment) {
    visitRequest?.abort(); previewRequest?.abort(); clearTimeout(previewTimer);
    const version=++visitVersion; ++quoteVersion;
    selected.value=appointment.public_id;
    router.replace({url:href('business.checkout.index')+'?appointment='+appointment.public_id+(props.filters?.location ? '&location='+props.filters.location : ''),preserveState:true,preserveScroll:true}); visit.value=null; sale.value=null; quote.value=null; lastQuote.value=null; error.value=''; quoteError.value=''; notice.value=''; loading.value=true; baseline.value='';
    visitRequest=new AbortController();
    try {
        const {data}=await axios.get(href('business.checkout.visit',appointment.public_id),{signal:visitRequest.signal});
        if (version!==visitVersion) return;
        visit.value=data; items.value=data.items; tips.value=[]; reason.value=''; applyDeposit.value=true; sale.value=data.sale;
        baseline.value=JSON.stringify(payload.value);
        if (sale.value) {resetPayment();loadPending();} else await calculate();
    } catch(exception) {if (!axios.isCancel(exception)) error.value=errorMessage(exception);}
    finally {if(version===visitVersion) loading.value=false;}
}
async function calculate() {
    if(!visit.value || sale.value || working.value) return;
    previewRequest?.abort(); const version=++quoteVersion; const appointmentId=selected.value;
    previewRequest=new AbortController(); calculating.value=true; quote.value=null; quoteError.value='';
    try {const {data}=await axios.post(href('business.checkout.preview',appointmentId),payload.value,{signal:previewRequest.signal}); if(version===quoteVersion && appointmentId===selected.value){quote.value=data.quote;lastQuote.value=data.quote;}}
    catch(exception) {if(!axios.isCancel(exception) && version===quoteVersion) quoteError.value=errorMessage(exception);}
    finally {if(version===quoteVersion) calculating.value=false;}
}
watch(payload,()=>{
    if(!visit.value || sale.value || loading.value) return;
    quote.value=null; calculating.value=true; previewRequest?.abort(); ++quoteVersion;clearTimeout(previewTimer);previewTimer=setTimeout(calculate,250);
},{deep:true,flush:'sync'});
async function prepare() {
    if(working.value || calculating.value || !quote.value || !visit.value) return;
    working.value=true; error.value='';
    try {
        const {data}=await axios.post(href('business.checkout.prepare',selected.value),{...payload.value,quote_key:quote.value.quote_key});
        sale.value=data.sale; baseline.value='';resetPayment();loadPending();notice.value=completed.value?'Sale completed.':'Sale saved. Record only payments you have received.';
        refreshLists();
    } catch(exception) {error.value=errorMessage(exception); if(exception.response?.status===422) await calculateAfterPrepare();}
    finally {working.value=false;}
}
async function calculateAfterPrepare() {working.value=false;await calculate();}
function addPayment() {
    if(!paymentValid.value || busy.value || payments.value.length>=8) return;
    payments.value.push({method:method.value,amount_minor:parsedAmount.value,reference:paymentReference.value || null});
    paymentAmount.value=moneyInput(staged.value.remaining,currency.value);paymentReference.value='';cashTendered.value='';received.value=false;
}
function removePayment(index) {if(busy.value)return;payments.value.splice(index,1);paymentAmount.value=moneyInput(staged.value.remaining,currency.value);received.value=false;}
const canRecord = computed(()=>received.value && (payments.value.length ? staged.value.valid : paymentValid.value) && !working.value && !!sale.value && sale.value.status==='open');
async function recordPayments() {
    if(!canRecord.value || pending.value) return;
    const rows=payments.value.length ? payments.value : [{method:method.value,amount_minor:parsedAmount.value,reference:paymentReference.value || null}];
    const command={idempotency_key:`checkout-${crypto.randomUUID()}`,received_confirmed:true,payments:rows};
    try {window.sessionStorage.setItem(storageKey(),JSON.stringify(command));}
    catch {error.value='Safe payment recovery is unavailable in this browser. Enable session storage before recording payment.';return;}
    pending.value=command;
    await submitPending();
}
async function submitPending() {
    if(!pending.value || working.value) return;
    working.value=true;error.value='';
    try {
        const {data}=await axios.post(href('business.checkout.payments',sale.value.public_id),pending.value);
        window.sessionStorage.removeItem(storageKey());pending.value=null;sale.value=data.sale;resetPayment();refreshLists();
        notice.value=completed.value?'Payment recorded. The sale is complete.':`Payment recorded. ${money(sale.value.balance_minor)} remains due.`;
    } catch(exception) {
        if(exception.response && [400,403,404,422].includes(exception.response.status)) {window.sessionStorage.removeItem(storageKey());pending.value=null;error.value=errorMessage(exception);}
        else error.value='The response was interrupted. The payment record may have been saved. Keep this sale open and retry the same request or refresh its payment history before taking any more money.';
    } finally {working.value=false;}
}
async function refreshSale() {
    if(!sale.value || working.value)return;working.value=true;error.value='';
    try {const {data}=await axios.get(href('business.checkout.show',sale.value.public_id));sale.value=data.sale; if(!pending.value)resetPayment();notice.value='Payment history refreshed.';}
    catch(exception){error.value=errorMessage(exception);} finally{working.value=false;}
}
async function applySavedDeposit() {
    if(working.value || pending.value)return;
    working.value=true;error.value='';
    try {const {data}=await axios.post(href('business.checkout.deposit',sale.value.public_id),{confirmed:true});sale.value=data.sale;resetPayment();refreshLists();notice.value='Appointment deposit applied.';}
    catch(exception){error.value=errorMessage(exception);}finally{working.value=false;}
}
const refreshLists=()=>router.reload({only:['appointments','sales','readyCount','salesPagination'],preserveScroll:true});

const catalogDialog=ref(null);const catalogKind=ref('service');const catalogSearch=ref('');const catalogItems=ref([]);const catalogLoading=ref(false);const catalogError=ref('');const catalogStaff=ref(null);const catalogNotice=ref('');
async function openCatalog(kind) {
    if(prepared.value || busy.value) return;
    catalogKind.value=kind;catalogSearch.value='';catalogNotice.value='';catalogStaff.value=eligibleStaff.value[0]?.id || null;
    catalogDialog.value?.open();await nextTick();document.querySelector('#co-catalog-search')?.focus();await searchCatalog();
}
async function searchCatalog() {
    catalogueRequest?.abort();const request=new AbortController();catalogueRequest=request;catalogLoading.value=true;catalogError.value='';
    try {const {data}=await axios.get(href('business.checkout.catalogue',selected.value),{params:{search:catalogSearch.value,kind:catalogKind.value},signal:request.signal});if(!request.signal.aborted)catalogItems.value=data.items;}
    catch(exception){if(!axios.isCancel(exception))catalogError.value=errorMessage(exception);}finally{if(!request.signal.aborted)catalogLoading.value=false;}
}
watch([catalogSearch,catalogKind],()=>{catalogItems.value=[];clearTimeout(catalogueTimer);catalogueTimer=setTimeout(searchCatalog,180);});
function addCatalogItem(item) {
    const variant=item.variants?.find(v=>v.staff_profile_id===catalogStaff.value);
    if(item.kind==='service' && !variant)return;
    const base=variant?.price_minor ?? item.price_minor;
    if(item.kind==='product') {
        const existing=items.value.find(row=>row.product_public_id===item.public_id && row.staff_profile_id===catalogStaff.value && !row.overridden && !row.discount_minor);
        const total=items.value.filter(row=>row.product_public_id===item.public_id).reduce((sum,row)=>sum+row.quantity,0);
        if(total>=item.stock){catalogError.value='All available stock is already in this sale.';return;}
        if(existing)existing.quantity++;else items.value.push({key:crypto.randomUUID(),product_public_id:item.public_id,description:item.name,kind:'product',quantity:1,stock:item.stock,base_price_minor:base,unit_price_minor:base,discount_minor:0,staff_profile_id:catalogStaff.value});
    } else items.value.push({key:crypto.randomUUID(),service_public_id:item.public_id,variants:item.variants,description:item.name,kind:'service',quantity:1,duration_minutes:item.duration_minutes,base_price_minor:base,unit_price_minor:base,discount_minor:0,staff_profile_id:catalogStaff.value});
    catalogError.value='';catalogNotice.value=`${item.name} added · ${items.value.length} items in sale`;
}

const editDialog=ref(null);const editingIndex=ref(-1);const edit=reactive({price:'',discount:'',discountType:'fixed',quantity:1,staff:null,reason:''});const editError=ref('');let syncingEdit=false;
const editBasePrice=computed(()=>{const item=items.value[editingIndex.value];return item?.service_public_id ? item.variants?.find(v=>v.staff_profile_id===edit.staff)?.price_minor ?? item.base_price_minor : item?.base_price_minor;});
watch(()=>edit.staff,(staff,previous)=>{const item=items.value[editingIndex.value];if(!syncingEdit && item?.service_public_id && staff!==previous){const variant=item.variants?.find(v=>v.staff_profile_id===staff);if(variant)edit.price=moneyInput(variant.price_minor,currency.value);}},{flush:'sync'});
function editItem(index) {if(prepared.value || busy.value)return;editingIndex.value=index;const item=items.value[index];syncingEdit=true;Object.assign(edit,{price:moneyInput(item.unit_price_minor,currency.value),discount:moneyInput(item.discount_minor,currency.value),discountType:'fixed',quantity:item.quantity,staff:item.staff_profile_id,reason:reason.value});syncingEdit=false;editError.value='';editDialog.value?.open();}
function saveItem(remove=false) {
    const item=items.value[editingIndex.value];const unit=parseMoney(edit.price,currency.value);
    const quantity=item.kind==='service' ? 1 : Number(edit.quantity);
    let discount=edit.discountType==='percent' ? Math.round((unit || 0)*quantity*Number(edit.discount)/100) : parseMoney(edit.discount,currency.value);
    if(remove) {if(!edit.reason.trim()){editError.value='Give a reason for removing this item.';return;}items.value.splice(editingIndex.value,1);reason.value=edit.reason;editDialog.value?.close();return;}
    if(unit===null || discount===null || !Number.isInteger(quantity) || quantity<1 || quantity>999 || discount>unit*quantity || (edit.discountType==='percent' && (!Number.isFinite(Number(edit.discount)) || Number(edit.discount)<0 || Number(edit.discount)>100))){editError.value='Check the price, quantity and discount. The discount cannot exceed this line.';return;}
    let base=item.base_price_minor;
    if(item.service_public_id && edit.staff!==item.staff_profile_id) {const variant=item.variants?.find(v=>v.staff_profile_id===edit.staff);if(!variant){editError.value='This staff member is not qualified for this service.';return;}base=variant.price_minor;}
    if((unit!==base || (item.booked_line_id && edit.staff!==item.staff_profile_id)) && !edit.reason.trim()){editError.value='Give a reason for a price or booked staff change.';return;}
    Object.assign(item,{unit_price_minor:unit,base_price_minor:base,overridden:unit!==base,quantity,discount_minor:discount,staff_profile_id:edit.staff});
    if(edit.reason)reason.value=edit.reason;editDialog.value?.close();
}

const tipDialog=ref(null);const tipStaff=ref(null);const tipAmount=ref('');const tipError=ref('');
function openTip() {if(prepared.value || busy.value)return;tipStaff.value=eligibleStaff.value.find(person=>items.value.some(item=>item.staff_profile_id===person.id))?.id || eligibleStaff.value[0]?.id || null;tipAmount.value=moneyInput(0,currency.value);tipError.value='';tipDialog.value?.open();}
function tipPercent(percent){tipAmount.value=moneyInput(Math.round((quote.value?.subtotal_minor-quote.value?.discount_minor || 0)*percent/100),currency.value);}
function saveTip(){const amount=parseMoney(tipAmount.value,currency.value);if(!tipStaff.value || amount===null){tipError.value='Choose staff and enter a valid tip.';return;}const old=tips.value.find(t=>t.staff_profile_id===tipStaff.value);if(old)old.amount_minor=amount;else tips.value.push({staff_profile_id:tipStaff.value,amount_minor:amount});tips.value=tips.value.filter(t=>t.amount_minor>0);tipDialog.value?.close();}
const switchDialog=ref(null);const switchTarget=ref(null);
const navigationTarget=ref(null);let stopNavigationGuard;
const confirmSwitch=()=>{baseline.value='';if(navigationTarget.value){const target=navigationTarget.value;navigationTarget.value=null;router.visit(target.url,target);}else loadVisit(switchTarget.value);};

const detailDialog=ref(null);const detail=ref(null);const detailLoading=ref(false);const detailError=ref('');
async function viewSale(publicId) {detail.value=null;detailError.value='';detailLoading.value=true;detailDialog.value?.open();try {const {data}=await axios.get(href('business.checkout.show',publicId));detail.value=data.sale;}catch(exception){detailError.value=errorMessage(exception);}finally{detailLoading.value=false;}}
const detailMoney=amount=>money(amount,detail.value?.currency_code || currency.value);
const refundDialog=ref(null);const refundSource=ref(null);const refund=reactive({amount:'',reason:'',kind:'refund',confirmed:false,allocations:[]});const refundError=ref('');const refundWorking=ref(false);const refundPending=ref(null);
watch([()=>refund.amount,()=>refund.reason,()=>refund.kind,()=>refund.allocations],()=>{if(!refundPending.value)refund.confirmed=false;},{deep:true});
function hasPendingRefund(payment){try{return !!window.sessionStorage.getItem(pendingStorageKey(userId.value,business.value,detail.value.public_id)+'-refund-'+payment.id);}catch{return false;}}
function openRefund(payment){
    refundSource.value=payment;try{refundPending.value=JSON.parse(window.sessionStorage.getItem(refundKey()));}catch{refundPending.value=null;}
    let remaining=payment.refundable_minor;
    const allocations=detail.value.lines.map(line=>{const available=Math.max(0,line.quantity*line.unit_price_minor-line.discount_minor-line.refunded_minor);const amount=Math.min(remaining,available);remaining-=amount;return {sale_line_id:line.id,description:line.description,kind:line.kind,amount:moneyInput(amount,detail.value.currency_code),quantity:0,disposition:line.kind==='product'?'customer_keeps':'not_applicable',remaining_quantity:line.quantity-line.returned_quantity};});
    Object.assign(refund,{amount:moneyInput(payment.refundable_minor,detail.value.currency_code),reason:'',kind:'refund',confirmed:false,allocations});
    if(refundPending.value){refund.amount=moneyInput(refundPending.value.amount_minor,detail.value.currency_code);refund.reason=refundPending.value.reason;refund.kind=refundPending.value.kind;refund.confirmed=true;}
    refundError.value='';detailDialog.value?.close();nextTick(()=>refundDialog.value?.open());
}
const refundKey=()=>pendingStorageKey(userId.value,business.value,detail.value.public_id)+'-refund-'+refundSource.value.id;
const checkRefundHistory=()=>{refundDialog.value?.close();viewSale(detail.value.public_id);};
async function recordRefund() {
    if(refundWorking.value)return;
    const amount=parseMoney(refund.amount,detail.value.currency_code);
    const allocations=refund.allocations.map(line=>({sale_line_id:line.sale_line_id,amount_minor:parseMoney(line.amount,detail.value.currency_code),quantity:Number(line.quantity),disposition:line.disposition}));
    if(!refundPending.value && (!refund.confirmed || !refund.reason.trim() || !amount || amount>refundSource.value.refundable_minor || allocations.some(line=>line.amount_minor===null || !Number.isInteger(line.quantity) || line.quantity<0) || allocations.reduce((sum,line)=>sum+line.amount_minor,0)>amount)){refundError.value='Confirm the returned payment, add a reason and review the refund amounts.';return;}
    refundPending.value ||= {idempotency_key:`refund-${crypto.randomUUID()}`,amount_minor:amount,reason:refund.reason,kind:refund.kind,returned_confirmed:true,line_refunds:allocations};
    try {window.sessionStorage.setItem(refundKey(),JSON.stringify(refundPending.value));}catch{refundPending.value=null;refundError.value='Safe refund recovery needs session storage enabled in this browser.';return;}
    refundWorking.value=true;refundError.value='';
    try {const {data}=await axios.post(route('business.checkout.refund',[business.value,detail.value.public_id,refundSource.value.id]),refundPending.value);window.sessionStorage.removeItem(refundKey());detail.value=data.sale;if(sale.value?.public_id===detail.value.public_id)sale.value=data.sale;refundPending.value=null;refundDialog.value?.close();nextTick(()=>detailDialog.value?.open());refreshLists();}
    catch(exception){if(exception.response && [400,403,404,422].includes(exception.response.status)){window.sessionStorage.removeItem(refundKey());refundPending.value=null;refundError.value=errorMessage(exception);}else refundError.value='The refund response was interrupted. Check the sale history or retry this same request. Do not return more money.';}
    finally{refundWorking.value=false;}
}

const historyError=ref('');
const historySearch=ref(props.filters?.search || '');const historyStatus=ref(props.filters?.status || '');const historyMethod=ref(props.filters?.method || '');const historyStaff=ref(props.filters?.staff || '');const historyFrom=ref(props.filters?.from || '');const historyTo=ref(props.filters?.to || '');const extraFilters=ref(false);const historyLoading=ref(false);let historyTimer;
watch(readySearch,()=>{clearTimeout(readySearchTimer);readySearchTimer=setTimeout(()=>router.get(href('business.checkout.index'),{ready_search:readySearch.value || undefined,appointment:selected.value || undefined,location:props.filters?.location || undefined},{only:['appointments','readyCount'],preserveState:true,preserveScroll:true,replace:true}),250);});
function filterHistory(){historyError.value='';if(historyFrom.value && historyTo.value && historyTo.value<historyFrom.value){historyError.value='The end date must be on or after the start date.';return;}historyLoading.value=true;router.get(href('business.checkout.index'),{section:'sales',search:historySearch.value || undefined,status:historyStatus.value || undefined,method:historyMethod.value || undefined,staff:historyStaff.value || undefined,from:historyFrom.value || undefined,to:historyTo.value || undefined,location:props.filters?.location || undefined},{only:['sales','salesPagination','filters'],preserveState:true,preserveScroll:true,replace:true,onError:errors=>historyError.value=Object.values(errors).flat()[0] || 'Review the filters.',onFinish:()=>historyLoading.value=false});}
watch(historySearch,()=>{clearTimeout(historyTimer);historyTimer=setTimeout(filterHistory,300);});
watch([historyStatus,historyMethod,historyStaff,historyFrom,historyTo],filterHistory);
function clearFilters(){historySearch.value='';historyStatus.value='';historyMethod.value='';historyStaff.value='';historyFrom.value='';historyTo.value='';}
function switchSection(value){section.value=value;const url=new URL(window.location.href);url.searchParams.set('section',value);router.replace({url:url.pathname+url.search,preserveState:true,preserveScroll:true});}
function changeLocation(value){if(working.value || pending.value)return;router.get(href('business.checkout.index'),{location:value || undefined,section:section.value},{preserveScroll:true});}
function beforeLeave(event){if(pending.value || (!prepared.value && dirty.value)){event.preventDefault();event.returnValue='';}}
onMounted(()=>{if(props.selectedSale)viewSale(props.selectedSale);if(props.permissions.checkout && (props.selectedVisit || props.appointments.length))loadVisit(props.selectedVisit || props.appointments.find(a=>a.public_id===props.selectedAppointment) || props.appointments[0]);window.addEventListener('beforeunload',beforeLeave);stopNavigationGuard=router.on('before',event=>{if(event.detail.visit.only?.length)return;if(working.value || pending.value || refundPending.value){event.preventDefault();error.value='Finish or recover the pending request before leaving checkout.';return;}if(!prepared.value && dirty.value){event.preventDefault();navigationTarget.value=event.detail.visit;switchDialog.value?.open();}});});
onBeforeUnmount(()=>{stopNavigationGuard?.();visitRequest?.abort();previewRequest?.abort();catalogueRequest?.abort();clearTimeout(previewTimer);clearTimeout(catalogueTimer);clearTimeout(historyTimer);clearTimeout(readySearchTimer);window.removeEventListener('beforeunload',beforeLeave);});
const summaryElement=ref(null);const focusPayment=()=>{(document.querySelector('.co-payment-block') || summaryElement.value)?.scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion:reduce)').matches?'instant':'smooth',block:'start'});};
</script>

<template>
    <AppLayout title="Checkout & sales" :business-label="page.props.tenant.name">
        <div class="co-workspace" :class="{'co-payment-nav-active':section==='checkout' && visit && !completed}">
            <PageHeader title="Checkout & sales">
                <template #actions><AppSelect v-if="locations.length>1" class="cd-input w-48" aria-label="Checkout location" :model-value="filters?.location || ''" :disabled="working || !!pending" @update:model-value="changeLocation"><option value="">All accessible locations</option><option v-for="location in locations" :key="location.public_id" :value="location.public_id">{{ location.name }}</option></AppSelect><AppButton v-if="permissions.calendar" :href="href('business.calendar')" variant="secondary"><CalendarDaysIcon class="size-4" aria-hidden="true" />Calendar</AppButton></template>
            </PageHeader>
            <div ref="tabsElement" class="co-tabs" role="tablist" aria-label="Checkout and sales sections"><button v-if="permissions.checkout" id="co-checkout-tab" type="button" role="tab" :aria-selected="section==='checkout'" :tabindex="section==='checkout'?0:-1" aria-controls="co-checkout-panel" @click="switchSection('checkout')" @keydown.right.prevent="permissions.history && (switchSection('sales'), tabsElement.querySelector('#co-sales-tab').focus())">Checkout <span class="co-count">{{ readyCount }}</span></button><button v-if="permissions.history" id="co-sales-tab" type="button" role="tab" :aria-selected="section==='sales'" :tabindex="section==='sales'?0:-1" aria-controls="co-sales-panel" @click="switchSection('sales')" @keydown.left.prevent="permissions.checkout && (switchSection('checkout'), tabsElement.querySelector('#co-checkout-tab').focus())">Sales history</button></div>

            <section v-show="section==='checkout'" v-if="permissions.checkout" id="co-checkout-panel" role="tabpanel" aria-labelledby="co-checkout-tab">
                <div class="co-heading co-ready-heading"><div><h2>Ready to checkout</h2><p>{{ readyCount }} {{ readyCount===1?'completed visit':'completed visits' }} awaiting payment<span v-if="readyCount>appointments.length"> · showing the latest {{ appointments.length }}</span></p></div><SearchField v-if="readyCount>4 || readySearch" v-model="readySearch" label="Find a visit" placeholder="Client or reference" :disabled="busy" class="co-ready-search" /><span class="co-status co-recording-label"><LockClosedIcon aria-hidden="true" />Recorded payments</span></div>
                <div v-if="appointments.length" class="co-visit-strip" aria-label="Visits awaiting payment"><button v-for="appointment in appointments" :key="appointment.public_id" class="co-visit" type="button" :aria-pressed="selected===appointment.public_id" :disabled="working || !!pending" @click="choose(appointment)"><span class="co-avatar" aria-hidden="true">{{ checkoutInitials(appointment.client) }}</span><span class="co-visit-info"><strong>{{ appointment.client }}</strong><small>{{ appointment.source==='walk_in'?'Walk-in':'Appointment' }} · {{ appointment.reference }}</small></span><span class="co-visit-amount">{{ money(appointment.sale?.balance_minor ?? appointment.price_minor,appointment.currency_code) }}<small>{{ appointment.sale?'Due':'Booked' }}</small></span></button></div>
                <div v-if="!appointments.length && !sale && !loading" class="co-panel co-empty"><ReceiptPercentIcon aria-hidden="true" /><h2>All caught up</h2><p>Completed visits appear here when they’re ready for checkout. Start or finish a visit from Calendar or Walk-in Queue.</p><AppButton v-if="permissions.calendar" :href="href('business.calendar')" variant="secondary">Open Calendar<ArrowRightIcon class="size-4" aria-hidden="true" /></AppButton></div>
                <p v-if="error && !visit" class="co-error" role="alert">{{ error }} <button class="co-text-action" @click="loadVisit({public_id:selected})">Retry loading visit</button></p>
                <div v-if="loading" class="co-layout" role="status" aria-label="Loading visit"><div class="co-panel co-loading"><div></div><div></div><div></div></div><div class="co-panel co-loading"><div></div><div></div><div></div></div></div>
                <div v-else-if="visit" class="co-layout">
                    <div class="co-main">
                        <div v-if="completed" class="co-panel co-success" tabindex="-1">
                            <span class="co-success-icon"><CheckCircleIcon aria-hidden="true" /></span><h2>{{ notice.includes('Payment') ? 'Payment recorded' : 'Sale completed' }}</h2><div class="co-success-amount">{{ money(sale.total_minor) }}</div><p><strong>{{ visit.appointment.client }}</strong> · {{ sale.transactions.filter(t=>t.kind==='payment').map(t=>paymentLabel(t.method)).filter((v,i,a)=>a.indexOf(v)===i).join(' + ') || (sale.deposit_applied_minor?'Covered by deposit':'No payment required') }}</p><p>{{ sale.receipt_number || 'Final receipt available' }}<br>Visit completed · {{ visit.appointment.reference }}<span v-if="sale.refunded_minor"> · {{ money(sale.refunded_minor) }} refunded</span></p>
                            <div class="co-success-actions"><a class="cd-app-button inline-flex items-center justify-center gap-2 border border-[var(--border-default)] bg-white text-sm font-semibold" :href="receiptHref(sale)" target="_blank" rel="noopener"><ReceiptPercentIcon class="size-4" aria-hidden="true" />Print / view receipt</a><AppButton v-if="permissions.calendar" :href="rebookHref(visit.appointment)" variant="secondary">Book next visit</AppButton><AppButton variant="quiet" @click="viewSale(sale.public_id)">View sale</AppButton><AppButton v-if="appointments.some(a=>a.public_id!==selected)" variant="primary" @click="loadVisit(appointments.find(a=>a.public_id!==selected))">Next checkout<ArrowRightIcon class="size-4" aria-hidden="true" /></AppButton><AppButton v-if="permissions.queue && visit.appointment.source==='walk_in'" :href="href('business.walk-ins.index')" variant="quiet">Return to queue</AppButton></div>
                        </div>
                        <div class="co-panel" :class="{'mt-4':completed}">
                            <div class="co-context"><span class="co-avatar" aria-hidden="true">{{ checkoutInitials(visit.appointment.client) }}</span><div class="co-client"><div class="flex items-baseline justify-between gap-3"><h2>{{ visit.appointment.client }}</h2><a v-if="visit.appointment.client_public_id" :href="href('business.clients.show',visit.appointment.client_public_id)">Client details</a></div><div class="co-meta"><span>{{ visit.appointment.reference }}</span><span>{{ visit.appointment.source==='walk_in'?'Walk-in':'Appointment' }}</span><span>{{ visit.appointment.location }}</span><br>{{ checkoutTime(visit.appointment.starts_at,visit.appointment.time_zone) }}<br><span>{{ visit.appointment.staff.join(', ') || 'Staff not attributed' }}</span><span>Visit completed</span></div></div></div>
                            <div class="co-section-head"><h3>Sale items <span class="ml-1">{{ prepared ? sale.lines.length : items.length }}</span></h3><span v-if="prepared" class="co-status"><LockClosedIcon aria-hidden="true" />Saved values</span><span v-else>{{ currency }} · {{ visit.tax_inclusive?'Tax included':'Tax added' }}</span></div>
                            <div v-if="prepared && !sale.lines.length" class="co-note">This older sale has no item snapshot. Its recorded totals and payment history are preserved.</div>
                            <div v-for="(item,index) in (prepared ? sale.lines : items)" :key="item.key || item.id" class="co-item"><span class="co-item-icon"><component :is="item.kind==='product'?ShoppingBagIcon:ScissorsIcon" aria-hidden="true" /></span><div><div class="co-item-name">{{ item.description }}</div><div class="co-item-meta">{{ item.kind==='product' ? `${item.quantity} × ${money(item.unit_price_minor)}` : `${prepared ? item.staff || 'Not attributed' : staffName(item.staff_profile_id)}${item.duration_minutes ? ' · '+item.duration_minutes+' min' : ''}` }}<span v-if="item.kind==='product' && item.staff_profile_id"> · {{ prepared ? item.staff : staffName(item.staff_profile_id) }}</span><span v-if="item.booked_line_id"> · Booked service</span><span v-if="item.overridden || item.base_price_minor!==item.unit_price_minor"> · Price adjusted</span><span v-if="item.discount_minor"> · −{{ money(item.discount_minor) }} discount</span></div></div><div class="co-line-money">{{ money(prepared ? item.line_total_minor : quote?.lines[index]?.line_total_minor ?? item.unit_price_minor*item.quantity-item.discount_minor) }}<small v-if="item.discount_minor">Before discount {{ money(item.unit_price_minor*item.quantity) }}</small></div><button v-if="!prepared" class="co-icon-button" type="button" :aria-label="`Edit ${item.description}`" :disabled="busy" @click="editItem(index)"><PencilSquareIcon aria-hidden="true" /></button><span v-else></span></div>
                            <div v-if="!items.length && !prepared" class="co-note">Add at least one service or product to save this sale.</div>
                            <div v-if="!prepared" class="co-add-row"><AppButton size="small" variant="secondary" :disabled="busy" @click="openCatalog('service')"><PlusIcon class="size-4" aria-hidden="true" />Add service</AppButton><AppButton v-if="permissions.retail" size="small" variant="secondary" :disabled="busy" @click="openCatalog('product')"><ShoppingBagIcon class="size-4" aria-hidden="true" />Add product</AppButton></div>
                            <div v-if="reason || sale?.adjustment_reason" class="co-note"><strong>Adjustment reason</strong> · {{ sale?.adjustment_reason || reason }}</div>
                            <div v-if="!prepared" class="co-note">Booked prices and performers are carried from the visit. Review changes before saving; saved sale items stay fixed.</div>
                        </div>
                        <div class="co-panel co-adjustments"><div class="co-adjustment-row"><div><h3>Tips</h3><p v-if="!(prepared ? sale.tips.length : tips.length)">Optional · attributed to the staff you choose</p><p v-for="(tip,index) in (prepared ? sale.tips : tips)" :key="index">{{ prepared ? tip.staff : staffName(tip.staff_profile_id) }} · {{ money(tip.amount_minor) }} <button v-if="!prepared" type="button" class="co-text-action ml-2" :aria-label="`Remove tip for ${staffName(tip.staff_profile_id)}`" :disabled="busy" @click="tips.splice(index,1)">Remove</button></p></div><AppButton v-if="!prepared" variant="quiet" size="small" :disabled="busy" @click="openTip">{{ tips.length?'Add / edit tip':'Add tip' }}</AppButton></div><div v-if="visit.deposit_available_minor && !prepared" class="co-adjustment-row"><div><h3>Deposit already paid</h3><p>{{ money(visit.deposit_available_minor) }} available for this visit</p></div><label class="flex items-center gap-2 text-xs font-semibold"><input v-model="applyDeposit" type="checkbox" class="cd-checkbox" :disabled="busy">Apply</label></div></div>
                        <div v-if="prepared && sale.transactions.length && !completed" class="co-panel co-adjustments mt-4"><div class="co-heading"><h2>Payments recorded</h2><button class="co-text-action" type="button" :disabled="working" @click="refreshSale"><ArrowPathIcon class="size-4 mr-1" aria-hidden="true" />Refresh</button></div><div v-for="payment in sale.transactions" :key="payment.public_id" class="co-ledger-row"><div><strong>{{ paymentLabel(payment.method) }}</strong><p>{{ payment.kind==='payment'?'Payment received':payment.kind==='void'?'Void recorded':'Refund recorded' }} · {{ checkoutTime(payment.occurred_at,visit.appointment.time_zone) }}</p></div><strong class="whitespace-nowrap tabular-nums">{{ payment.kind==='payment'?'':'−' }}{{ money(payment.amount_minor) }}</strong></div></div>
                    </div>
                    <aside ref="summaryElement" class="co-summary co-panel" aria-label="Sale summary and payment">
                        <div class="co-summary-top"><span class="co-eyebrow">{{ completed?'Sale total':'Amount due' }}</span><strong class="co-due">{{ totals ? money(completed?sale.total_minor:totals.balance_minor) : '—' }}</strong><p class="co-due-caption">{{ completed?'Original sale · payment history retained':calculating?'Updating totals…':prepared?'Saved sale · ready to record payment':'Review before recording payment' }}</p></div>
                        <div class="co-summary-body">
                            <dl v-if="totals" class="co-totals"><div><dt>Subtotal</dt><dd>{{ money(totals.subtotal_minor) }}</dd></div><div v-if="totals.discount_minor"><dt>Item discounts</dt><dd>−{{ money(totals.discount_minor) }}</dd></div><div v-if="totals.tax_minor"><dt>{{ totals.tax_inclusive?'Tax included':'Tax' }}</dt><dd>{{ money(totals.tax_minor) }}</dd></div><div v-if="totals.tip_minor"><dt>Tips</dt><dd>{{ money(totals.tip_minor) }}</dd></div><div class="co-total"><dt>Sale total</dt><dd>{{ money(totals.total_minor) }}</dd></div><div v-if="totals.deposit_applied_minor" class="co-credit"><dt>Deposit already paid</dt><dd>−{{ money(totals.deposit_applied_minor) }}</dd></div><div v-if="sale?.paid_minor" class="co-credit"><dt>Payments received</dt><dd>−{{ money(sale.paid_minor) }}</dd></div><div v-if="sale?.refunded_minor"><dt>Refunds / voids</dt><dd>{{ money(sale.refunded_minor) }}</dd></div><div v-if="!completed || sale.balance_minor" class="co-total"><dt>{{ completed?'Recorded ledger balance':'Amount due' }}</dt><dd>{{ money(totals.balance_minor) }}</dd></div></dl>
                            <p v-if="quote?.deposit_excess_minor" class="co-info">{{ money(quote.deposit_excess_minor) }} of the deposit remains unallocated. Review it separately; it is not another payment due.</p>
                            <p v-if="quoteError" class="co-error" role="alert">{{ quoteError }}</p><p v-if="error" class="co-error" role="alert">{{ error }}</p>
                            <div v-if="pending" class="co-info"><strong>Payment confirmation pending</strong><br>This exact request is saved for safe recovery. Do not take payment again.<div class="flex flex-wrap gap-2 mt-3"><AppButton size="small" :loading="working" @click="submitPending">Retry same request</AppButton><AppButton size="small" variant="secondary" :disabled="working" @click="refreshSale">Check history</AppButton></div></div>
                            <div v-if="!prepared" class="co-payment-actions"><AppButton :loading="working" :disabled="!quote || calculating" @click="prepare">{{ calculating?'Updating totals…':'Review & save sale' }}<ArrowRightIcon class="size-4" aria-hidden="true" /></AppButton><p class="co-manual-note">Saves the reviewed items and applies the selected deposit. Payment is recorded next.</p></div>
                            <div v-else-if="!completed && !pending" class="co-payment-block">
                                <div v-if="sale.deposit_available_minor" class="co-info">{{ money(sale.deposit_available_minor) }} deposit is available for this appointment.<AppButton size="small" variant="secondary" class="mt-2" :disabled="working" @click="applySavedDeposit">Apply appointment deposit</AppButton></div><h3>Record payment received</h3><div v-if="staged.remaining>0"><fieldset :disabled="working"><legend class="sr-only">Payment method</legend><div class="co-methods"><label v-for="item in visibleMethods" :key="item.id"><input v-model="method" class="sr-only" type="radio" :value="item.id" name="payment-method"><component :is="item.icon" aria-hidden="true" /><span>{{ item.label }}</span></label></div></fieldset>
                                <div class="co-fields"><div class="co-field"><label for="co-payment-amount">Amount received · {{ currency }}</label><input id="co-payment-amount" v-model="paymentAmount" class="cd-input" inputmode="decimal" :disabled="working" :aria-invalid="!!paymentAmount && !paymentValid || undefined"></div><div v-if="method==='cash'" class="co-field"><label for="co-cash-tendered">Cash tendered <span class="font-normal">(optional)</span></label><input id="co-cash-tendered" v-model="cashTendered" class="cd-input" inputmode="decimal" :disabled="working"></div></div>
                                <p v-if="cashChange!==null" :class="cashChange<0?'co-error':'co-item-meta mt-2'">{{ cashChange<0?'Cash tendered is below the recorded amount.':`Change due ${money(cashChange)}` }}</p>
                                <div v-if="method!=='cash'" class="co-field"><label for="co-payment-reference">Reference <span class="font-normal">(optional)</span></label><input id="co-payment-reference" v-model="paymentReference" class="cd-input" maxlength="191" :disabled="working" placeholder="Terminal or transfer reference"></div>
                                <button v-if="payments.length<8 && staged.remaining>0" type="button" class="co-text-action mt-2" :disabled="!paymentValid || working" @click="addPayment"><PlusIcon class="size-4 mr-1" aria-hidden="true" />Add to split payment</button>
                                </div><div v-for="(payment,index) in payments" :key="index" class="co-payment-row"><span>{{ paymentLabel(payment.method) }}</span><strong>{{ money(payment.amount_minor) }}</strong><button class="co-icon-button" type="button" :aria-label="`Remove ${paymentLabel(payment.method)} split`" :disabled="working" @click="removePayment(index)"><TrashIcon aria-hidden="true" /></button></div>
                                <p v-if="payments.length" class="co-item-meta mt-2">Payments entered {{ money(staged.total) }} · remaining {{ money(staged.remaining) }}</p>
                                <p v-else-if="paymentValid && parsedAmount<sale.balance_minor" class="co-info">Partial payment · {{ money(sale.balance_minor-parsedAmount) }} will remain outstanding.</p>
                                <label class="co-received"><input v-model="received" type="checkbox" class="cd-checkbox" :disabled="working"><span>I have received {{ money(payments.length?staged.total:parsedAmount || 0) }}. This records payment; no card is charged here.</span></label>
                                <div class="co-payment-actions"><AppButton :loading="working" :disabled="!canRecord" @click="recordPayments">Record {{ money(payments.length?staged.total:parsedAmount || 0) }}{{ (payments.length?staged.remaining:sale.balance_minor-(parsedAmount || 0))===0?' & complete':' payment' }}</AppButton><AppButton v-if="permissions.calendar" variant="quiet" :href="href('business.calendar')">Pay later · keep {{ money(sale.balance_minor) }} outstanding</AppButton></div><p class="co-manual-note">External card and online payments must already be successful. Pending or failed payments are not receipts.</p>
                            </div>
                            <p v-if="notice" class="co-live-message" role="status">{{ notice }}</p><div v-if="completed" class="co-payment-actions"><AppButton variant="secondary" @click="viewSale(sale.public_id)">Review sale & payments</AppButton></div>
                        </div>
                    </aside>
                </div>
                <div v-if="visit && !completed" class="co-bottom"><div><small>Amount due</small><strong>{{ totals ? money(totals.balance_minor) : '—' }}</strong></div><AppButton variant="primary" @click="focusPayment">{{ prepared?'Payment':'Review totals' }}<ArrowRightIcon class="size-4" aria-hidden="true" /></AppButton></div>
            </section>

            <section v-show="section==='sales'" v-if="permissions.history" id="co-sales-panel" role="tabpanel" aria-labelledby="co-sales-tab" class="co-panel">
                <div class="co-history-toolbar"><SearchField v-model="historySearch" label="Search sales" placeholder="Client, sale or visit reference" /><div class="co-filter"><label for="co-history-status">Status</label><AppSelect id="co-history-status" v-model="historyStatus" class="cd-input"><option value="">All sales</option><option value="open">Outstanding</option><option value="completed">Completed</option><option value="refunded">Refunded / voided</option></AppSelect></div><div class="co-filter"><label for="co-history-method">Payment method</label><AppSelect id="co-history-method" v-model="historyMethod" class="cd-input"><option value="">All methods</option><option v-for="item in visibleMethods" :key="item.id" :value="item.id">{{ item.label }}</option></AppSelect></div><AppButton variant="secondary" :aria-expanded="extraFilters" aria-controls="co-extra-filters" @click="extraFilters=!extraFilters">More filters</AppButton></div>
                <div v-if="extraFilters" id="co-extra-filters" class="co-filter-extra"><div class="co-filter"><label for="co-history-staff">Attributed staff</label><AppSelect id="co-history-staff" v-model="historyStaff" class="cd-input"><option value="">All staff</option><option v-for="person in staffFilters" :key="person.id" :value="person.id">{{ person.display_name }}</option></AppSelect></div><div class="co-filter"><label for="co-history-from">From · location date</label><input id="co-history-from" v-model="historyFrom" class="cd-input" type="date"></div><div class="co-filter"><label for="co-history-to">To · location date</label><input id="co-history-to" v-model="historyTo" class="cd-input" type="date" :min="historyFrom"></div><AppButton variant="quiet" class="self-end" @click="clearFilters">Clear filters</AppButton></div>
                <div class="co-history-meta" role="status"><span>{{ historyLoading?'Updating sales…':`${salesPagination?.total || 0} sales` }}</span><span>Original values · refunds retained separately</span></div>
                <p v-if="historyError" class="co-error" role="alert">{{ historyError }}</p><DataTable v-if="sales.length" caption="Sales history" class="co-history-table"><thead><tr><th scope="col">Client / sale</th><th scope="col">Visit & location</th><th scope="col">Status / payment</th><th scope="col" class="text-right">Total</th><th scope="col" class="text-right">Outstanding</th><th scope="col"><span class="sr-only">View sale</span></th></tr></thead><tbody><tr v-for="record in sales" :key="record.public_id"><td class="cd-record-primary"><button class="co-text-action text-left" @click="viewSale(record.public_id)">{{ record.client || 'Client' }}</button><p class="cd-record-meta">{{ checkoutTime(record.created_at,record.time_zone) }}</p><p class="cd-record-meta">{{ record.public_id.slice(-10) }}</p></td><td><p class="text-xs font-semibold">{{ record.reference }}</p><p class="cd-record-meta">{{ record.location }}<br>{{ record.staff?.join(', ') }}</p></td><td><span :class="['co-status',record.status==='completed'?'co-status-paid':'']"><CheckCircleIcon v-if="record.status==='completed'" aria-hidden="true" />{{ record.status==='completed'?'Completed':'Outstanding' }}</span><p v-if="record.methods.length" class="cd-record-meta">{{ record.methods.map(paymentLabel).join(' + ') }}</p><p v-if="record.refunded_minor" class="cd-record-meta">{{ money(record.refunded_minor,record.currency_code) }} refunded / voided</p></td><td class="co-line-money">{{ money(record.total_minor,record.currency_code) }}</td><td class="co-line-money">{{ record.status==='completed' && record.refunded_minor ? '—' : money(record.balance_minor,record.currency_code) }}</td><td><button type="button" class="co-icon-button" :aria-label="`View sale for ${record.client || 'client'}`" @click="viewSale(record.public_id)"><ArrowRightIcon aria-hidden="true" /></button></td></tr></tbody></DataTable>
                <div v-else class="co-empty"><ReceiptPercentIcon aria-hidden="true" /><h2>{{ historySearch || historyStatus || historyMethod ? 'No matching sales' : 'Your sales will appear here' }}</h2><p>{{ historySearch || historyStatus || historyMethod ? 'Try a different reference or clear the filters.' : 'Saved and completed checkouts appear here with their payments and outstanding balance.' }}</p></div>
                <div v-if="salesPagination?.last_page>1" class="co-history-meta"><span>Page {{ salesPagination.current_page }} of {{ salesPagination.last_page }}</span><div class="flex gap-2"><AppButton size="small" variant="secondary" :href="salesPagination.prev || undefined" :disabled="!salesPagination.prev">Previous</AppButton><AppButton size="small" variant="secondary" :href="salesPagination.next || undefined" :disabled="!salesPagination.next">Next</AppButton></div></div>
            </section>

            <AppDialog ref="catalogDialog" title="Add to this sale" :close-on-confirm="false"><div class="co-catalog-tabs"><button type="button" :aria-pressed="catalogKind==='service'" @click="catalogKind='service'">Services</button><button v-if="permissions.retail" type="button" :aria-pressed="catalogKind==='product'" @click="catalogKind='product'">Retail products</button></div><div class="co-field"><label for="co-catalog-search">{{ catalogKind==='product'?'Search name, SKU or barcode':'Search services' }}</label><input id="co-catalog-search" v-model="catalogSearch" class="cd-input" type="search" autocomplete="off" placeholder="Start typing…" @keydown.down.prevent="focusCatalogResult" @keydown.enter.prevent="catalogItems.length===1 && addCatalogItem(catalogItems[0])"></div><div class="co-field"><label for="co-catalog-staff">{{ catalogKind==='service'?'Performed by':'Sales attribution (optional)' }}</label><AppSelect id="co-catalog-staff" :aria-label="catalogKind==='service'?'Performed by':'Sales attribution (optional)'" v-model="catalogStaff" class="cd-input"><option v-if="catalogKind==='product'" :value="null">No staff attribution</option><option v-for="person in visit?.staff.filter(s=>s.status==='active') || []" :key="person.id" :value="person.id">{{ person.display_name }}</option></AppSelect></div><p v-if="catalogError" class="co-error" role="alert">{{ catalogError }}</p><p v-if="catalogNotice" class="co-live-message" role="status">{{ catalogNotice }}</p><p v-if="catalogLoading" class="co-item-meta mt-4" role="status">Searching catalogue…</p><div v-else class="co-catalog-list"><button v-for="item in catalogItems" :key="item.public_id" type="button" class="co-catalog-result" :disabled="item.kind==='product' ? item.stock<1 : !item.variants.some(v=>v.staff_profile_id===catalogStaff)" @click="addCatalogItem(item)"><span><strong>{{ item.name }}</strong><small>{{ item.category || (item.kind==='product'?'Retail':'Service') }}<span v-if="item.kind==='product'"> · {{ item.sku }} · {{ item.stock>0?item.stock+' in stock':'Out of stock' }}</span><span v-else> · {{ item.duration_minutes }} min{{ !item.variants.some(v=>v.staff_profile_id===catalogStaff)?' · Choose qualified staff':'' }}</span></small></span><span>{{ money(item.variants?.find(v=>v.staff_profile_id===catalogStaff)?.price_minor ?? item.price_minor) }}</span><PlusIcon class="size-4 shrink-0" aria-hidden="true" /></button><p v-if="!catalogItems.length" class="co-item-meta py-6">No matching {{ catalogKind==='product'?'products':'services' }} at this location.</p></div><template #footer><span class="text-xs text-[var(--text-muted)] mr-auto">Current catalogue · prices verified before saving</span><AppButton variant="secondary" @click="catalogDialog.close()">Done</AppButton></template></AppDialog>

            <AppDialog ref="editDialog" :title="`Edit ${items[editingIndex]?.description || 'sale item'}`" :close-on-confirm="false" confirm-label="Save item" @confirm="saveItem()"><div class="co-fields"><div class="co-field"><label for="co-item-price">Unit price · {{ currency }}</label><input id="co-item-price" v-model="edit.price" class="cd-input" inputmode="decimal" :disabled="!permissions.override"><small>Catalogue / booked {{ money(editBasePrice) }}{{ permissions.override?' · changes need a reason':'' }}</small></div><div v-if="items[editingIndex]?.kind==='product'" class="co-field"><label for="co-item-quantity">Quantity</label><input id="co-item-quantity" v-model="edit.quantity" type="number" class="cd-input" min="1" max="999" step="1"><small>Available stock checked before saving</small></div></div><div class="co-field"><label for="co-item-staff">{{ items[editingIndex]?.kind==='product'?'Sales attribution':'Performed by' }}</label><AppSelect id="co-item-staff" :aria-label="items[editingIndex]?.kind==='product'?'Sales attribution':'Performed by'" v-model="edit.staff" class="cd-input" :disabled="!!items[editingIndex]?.booked_line_id && !permissions.override"><option :value="null">Not attributed</option><option v-for="person in visit?.staff || []" :key="person.id" :value="person.id" :disabled="!person.eligible_for_addition && person.id!==items[editingIndex]?.staff_profile_id">{{ person.display_name }}</option></AppSelect></div><div v-if="permissions.discount" class="co-fields"><div class="co-field"><label for="co-discount-type">Discount on this item</label><AppSelect id="co-discount-type" v-model="edit.discountType" class="cd-input"><option value="fixed">Fixed amount · {{ currency }}</option><option value="percent">Percentage</option></AppSelect></div><div class="co-field"><label for="co-item-discount">{{ edit.discountType==='percent'?'Discount %':'Discount amount' }}</label><input id="co-item-discount" v-model="edit.discount" class="cd-input" inputmode="decimal"></div></div><div class="co-field"><label for="co-adjustment-reason">Reason for adjustment</label><textarea id="co-adjustment-reason" v-model="edit.reason" class="cd-input" rows="2" maxlength="1000" placeholder="Required for removal, price or booked staff changes"></textarea></div><p class="co-item-meta mt-3">Staff attribution and discounts can affect commission. Saved sales cannot be edited here.</p><p v-if="editError" class="co-error" role="alert">{{ editError }}</p><button type="button" class="co-text-action text-[var(--status-danger)] mt-3" @click="saveItem(true)"><TrashIcon class="size-4 mr-1" aria-hidden="true" />Remove this item</button></AppDialog>

            <AppDialog ref="tipDialog" title="Add a staff tip" confirm-label="Save tip" :close-on-confirm="false" @confirm="saveTip"><p class="text-sm text-[var(--text-muted)]">Tips are added to the total and recorded separately from commission. Add one allocation for each staff member.</p><div class="co-field"><label for="co-tip-staff">Tip for</label><AppSelect id="co-tip-staff" v-model="tipStaff" class="cd-input"><option v-for="person in eligibleStaff" :key="person.id" :value="person.id">{{ person.display_name }}</option></AppSelect></div><div class="flex flex-wrap gap-2 mt-4"><AppButton size="small" variant="secondary" @click="tipPercent(0)">No tip</AppButton><AppButton v-for="percent in [10,15,20]" :key="percent" size="small" variant="secondary" :disabled="calculating || !quote" @click="tipPercent(percent)">{{ percent }}%</AppButton></div><p class="co-item-meta mt-2">Percentages use the discounted subtotal.</p><div class="co-field"><label for="co-tip-amount">Tip amount · {{ currency }}</label><input id="co-tip-amount" v-model="tipAmount" class="cd-input" inputmode="decimal"></div><p v-if="tipError" class="co-error" role="alert">{{ tipError }}</p></AppDialog>
            <AppDialog ref="switchDialog" title="Leave this unsaved sale?" description="Your item changes have not been saved. Switching visits discards these changes; no payment has been recorded." confirm-label="Discard & continue" @confirm="confirmSwitch" />

            <AppDialog ref="detailDialog" title="Sale detail" drawer :close-on-confirm="false"><div v-if="detailLoading" class="co-loading" role="status" aria-label="Loading sale"><div></div><div></div><div></div></div><p v-if="detailError" class="co-error" role="alert">{{ detailError }}</p><template v-if="detail"><div class="co-heading"><div><h2>{{ detail.appointment?.client || 'Client' }}</h2><p>{{ detail.appointment?.reference }} · {{ detail.appointment?.location }}</p></div><span class="co-status" :class="{'co-status-paid':detail.status==='completed'}">{{ detail.status==='completed'?'Completed':'Outstanding' }}</span></div><p class="co-item-meta">Sale {{ detail.public_id }}<br>{{ checkoutTime(detail.created_at,detail.appointment?.time_zone) }}</p><div class="co-section-head px-0"><h3>Original items</h3></div><p v-if="!detail.lines.length" class="co-info">This older sale has no item snapshot. Recorded totals are retained.</p><div v-for="item in detail.lines" :key="item.id" class="co-ledger-row"><div><strong>{{ item.description }}</strong><p>{{ item.quantity }} × {{ detailMoney(item.unit_price_minor) }} · {{ item.staff || 'Not attributed' }}<span v-if="item.discount_minor"><br>Discount −{{ detailMoney(item.discount_minor) }}</span><span v-if="item.refunded_minor"><br>{{ detailMoney(item.refunded_minor) }} refunded / voided</span></p></div><strong class="whitespace-nowrap tabular-nums">{{ detailMoney(item.line_total_minor) }}</strong></div><div class="co-detail-grid"><dl class="co-totals"><div><dt>Subtotal</dt><dd>{{ detailMoney(detail.subtotal_minor) }}</dd></div><div v-if="detail.discount_minor"><dt>Discount</dt><dd>−{{ detailMoney(detail.discount_minor) }}</dd></div><div v-if="detail.tax_minor"><dt>{{ detail.tax_inclusive?'Tax included':'Tax' }}</dt><dd>{{ detailMoney(detail.tax_minor) }}</dd></div><div v-if="detail.tip_minor"><dt>Tips</dt><dd>{{ detailMoney(detail.tip_minor) }}</dd></div><div class="co-total"><dt>Original total</dt><dd>{{ detailMoney(detail.total_minor) }}</dd></div></dl><dl class="co-totals"><div v-if="detail.deposit_applied_minor"><dt>Deposit applied</dt><dd>{{ detailMoney(detail.deposit_applied_minor) }}</dd></div><div><dt>Payments received</dt><dd>{{ detailMoney(detail.paid_minor) }}</dd></div><div v-if="detail.refunded_minor"><dt>Refunds / voids</dt><dd>−{{ detailMoney(detail.refunded_minor) }}</dd></div><div><dt>Net received</dt><dd>{{ detailMoney(detail.paid_minor+detail.deposit_applied_minor-detail.refunded_minor) }}</dd></div><div class="co-total"><dt>{{ detail.status==='completed' && detail.refunded_minor?'Recorded ledger balance':'Amount due' }}</dt><dd>{{ detailMoney(detail.balance_minor) }}</dd></div></dl></div><p v-if="detail.refunded_minor" class="co-info">Refunds are append-only adjustments. This completed sale’s recorded balance reflects those adjustments; it is not reopened for another charge.</p><p v-if="detail.adjustment_reason" class="co-item-meta mt-3">Adjustment reason · {{ detail.adjustment_reason }}</p><div class="co-section-head px-0 mt-4"><h3>Payment history</h3></div><p v-if="!detail.transactions.length" class="co-item-meta">{{ detail.deposit_applied_minor?'This sale uses an appointment deposit.':'No payments recorded yet.' }}</p><div v-for="payment in detail.transactions" :key="payment.public_id" class="co-ledger-row"><div><strong>{{ paymentLabel(payment.method) }}</strong><p>{{ payment.kind==='payment'?'Payment received':payment.kind==='void'?'Void recorded':'Refund recorded' }} · {{ checkoutTime(payment.occurred_at,detail.appointment?.time_zone) }}<span v-if="payment.recorded_by"><br>Recorded by {{ payment.recorded_by }}</span><span v-if="payment.reason"><br>{{ payment.reason }}</span></p><button v-if="permissions.refund && (payment.refundable_minor>0 || hasPendingRefund(payment)) && !payment.provider_managed" type="button" class="co-text-action text-[var(--status-danger)] mt-2" @click="openRefund(payment)">{{ hasPendingRefund(payment)?'Recover pending refund':'Record refund / void' }}</button><p v-else-if="permissions.refund && payment.refundable_minor>0 && payment.provider_managed" class="co-item-meta">Provider refund requires approved reconciliation.</p></div><strong class="whitespace-nowrap tabular-nums">{{ payment.kind==='payment'?'':'−' }}{{ detailMoney(payment.amount_minor) }}</strong></div><p v-if="detail.receipt_number" class="co-item-meta mt-4">{{ detail.receipt_number }} · original issued receipt retained.</p></template><template #footer><AppButton variant="secondary" @click="detailDialog.close()">Close</AppButton><a v-if="detail?.status==='completed'" class="cd-app-button inline-flex items-center justify-center gap-2 border border-[var(--border-default)] bg-white text-sm font-semibold" :href="receiptHref(detail)" target="_blank" rel="noopener">Print / view receipt</a><AppButton v-else-if="detail?.appointment && permissions.checkout" :href="href('business.checkout.index')+'?appointment='+detail.appointment.public_id">Continue checkout</AppButton></template></AppDialog>

            <AppDialog ref="refundDialog" title="Record refund / void" :description="detail && refundSource ? `${detail.appointment?.client} · ${detail.appointment?.reference}. ${paymentLabel(refundSource.method)} · ${detailMoney(refundSource.refundable_minor)} refundable. Record money already returned through the original method; this does not send a provider refund.` : 'Review the original payment and stock disposition.'" destructive :close-on-confirm="false" :can-close="()=>!refundWorking && !refundPending" :confirm-disabled="refundWorking || (!refundPending && !refund.confirmed)" :confirm-label="refundWorking?'Recording…':refundPending?'Retry same refund':'Confirm refund record'" @confirm="recordRefund"><template v-if="detail && refundSource"><div class="co-info">{{ detail.appointment?.client }} · {{ detail.appointment?.reference }}<br>Original method: {{ paymentLabel(refundSource.method) }}<br>Refundable: {{ detailMoney(refundSource.refundable_minor) }}</div><fieldset :disabled="refundWorking || !!refundPending"><div class="co-fields"><div class="co-field"><label for="co-refund-kind">Adjustment</label><AppSelect id="co-refund-kind" v-model="refund.kind" class="cd-input"><option value="refund">Refund</option><option value="void">Void recorded payment</option></AppSelect></div><div class="co-field"><label for="co-refund-amount">Total to record · {{ detail.currency_code }}</label><input id="co-refund-amount" v-model="refund.amount" class="cd-input" inputmode="decimal"></div></div><p class="co-item-meta mt-3">Allocate item amounts below. Any remaining refund is unallocated tax or tip; original sale values are retained.</p><div v-for="line in refund.allocations" :key="line.sale_line_id" class="co-refund-line"><strong>{{ line.description }}</strong><div class="co-fields"><div class="co-field"><label :for="`refund-line-${line.sale_line_id}`">Item refund · {{ detail.currency_code }}</label><input :id="`refund-line-${line.sale_line_id}`" :aria-label="`Item refund for ${line.description} · ${detail.currency_code}`" v-model="line.amount" class="cd-input" inputmode="decimal"></div><div v-if="line.kind==='product'" class="co-field"><label :for="`refund-quantity-${line.sale_line_id}`">Returned quantity</label><input :id="`refund-quantity-${line.sale_line_id}`" :aria-label="`Returned quantity for ${line.description}`" v-model="line.quantity" type="number" class="cd-input" min="0" :max="line.remaining_quantity"><small>{{ line.remaining_quantity }} units not yet returned</small></div></div><div v-if="line.kind==='product'" class="co-field"><label :for="`refund-disposition-${line.sale_line_id}`">Stock disposition</label><AppSelect :id="`refund-disposition-${line.sale_line_id}`" v-model="line.disposition" class="cd-input"><option value="customer_keeps">Client keeps product</option><option value="restock">Return to stock</option><option value="write_off">Returned · write off</option></AppSelect></div></div><div class="co-field"><label for="co-refund-reason">Reason (required)</label><textarea id="co-refund-reason" v-model="refund.reason" class="cd-input" rows="2" maxlength="1000"></textarea></div><label class="co-received"><input v-model="refund.confirmed" type="checkbox" class="cd-checkbox"><span>I have returned this amount via {{ paymentLabel(refundSource.method) }} and reviewed the stock disposition.</span></label></fieldset><div v-if="refundPending" class="co-info">This refund request is saved for recovery. Retry the same request or check its recorded history.<AppButton variant="secondary" size="small" :disabled="refundWorking" @click="checkRefundHistory">Check sale history</AppButton></div><p v-if="refundError" class="co-error" role="alert">{{ refundError }}</p></template></AppDialog>
        </div>
    </AppLayout>
</template>
