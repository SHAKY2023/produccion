// Auditor Nomina:index.html - Versión: v1.9.0
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor de Liquidaciones</title>
    
    <!-- React y ReactDOM -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    
    <!-- Babel para compilar JSX en el navegador -->
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    
    <!-- Tailwind CSS para estilos -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Estilos para impresión -->
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect, useMemo } = React;

        // --- ICONOS SVG ---
        const Icons = {
            Calculator: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><path d="M16 10h.01"/><path d="M12 10h.01"/><path d="M8 10h.01"/><path d="M12 14h.01"/><path d="M8 14h.01"/><path d="M12 18h.01"/><path d="M8 18h.01"/></svg>,
            Calendar: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>,
            AlertTriangle: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>,
            CheckCircle: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>,
            XCircle: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>,
            FileText: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>,
            DollarSign: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>,
            Clock: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>,
            Printer: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>,
            Scale: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>,
            Image: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>,
            MinusCircle: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>,
            ToggleLeft: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="8" cy="12" r="2"/></svg>,
            ToggleRight: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect width="20" height="12" x="2" y="6" rx="6" ry="6"/><circle cx="16" cy="12" r="2"/></svg>,
            Gift: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.9 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"/></svg>
        };

        // --- COMPONENTES UI ---

        const Card = ({ children, className = "" }) => (
            <div className={`bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden ${className}`}>
                {children}
            </div>
        );

        const SectionTitle = ({ icon, title }) => {
            const IconComp = Icons[icon] || Icons.FileText;
            return (
                <div className="flex items-center gap-2 mb-4 text-slate-800 font-bold text-lg border-b pb-2">
                    <IconComp className="w-5 h-5 text-blue-600" />
                    <h3>{title}</h3>
                </div>
            );
        };

        const InputGroup = ({ label, value, onChange, type = "text", placeholder = "", prefix = null }) => (
            <div className="mb-3">
                <label className="block text-xs font-semibold text-slate-500 uppercase mb-1">{label}</label>
                <div className="relative">
                    {prefix && (
                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span className="text-slate-500 sm:text-sm">{prefix}</span>
                        </div>
                    )}
                    <input
                        type={type}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        className={`w-full p-2 ${prefix ? 'pl-7' : ''} bg-slate-50 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm`}
                        placeholder={placeholder}
                    />
                </div>
            </div>
        );

        const ResultRow = ({ label, calculated, document, isCurrency = true, tolerance = 100 }) => {
            const diff = Math.abs(calculated - document);
            const isMatch = diff <= tolerance;
            const format = (val) => isCurrency ? new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(val) : val;

            return (
                <div className={`flex justify-between items-center p-3 rounded-lg mb-2 ${isMatch ? 'bg-green-50' : 'bg-red-50'} print:border print:border-slate-200`}>
                    <span className="text-sm font-medium text-slate-700">{label}</span>
                    <div className="flex items-center gap-4 text-sm">
                        <div className="text-right">
                            <div className="text-xs text-slate-500">Legal/Sel.</div>
                            <div className="font-bold text-slate-800">{format(calculated)}</div>
                        </div>
                        <div className="text-right">
                            <div className="text-xs text-slate-500">PDF</div>
                            <div className={`font-bold ${isMatch ? 'text-green-600' : 'text-red-600'}`}>{format(document)}</div>
                        </div>
                        {isMatch ? <Icons.CheckCircle className="w-5 h-5 text-green-500 no-print" /> : <Icons.XCircle className="w-5 h-5 text-red-500 no-print" />}
                    </div>
                </div>
            );
        };

        const BaseInput = ({ label, calculatedBase, value, onChange, usePdfBase, onToggleBase }) => {
             const diff = Math.abs(calculatedBase - value);
             const isMatch = value == 0 ? true : diff <= 100;
             const format = (val) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(val);

             return (
                 <div className="mb-4 p-3 bg-slate-50 rounded-lg border border-slate-200 relative">
                    <div className="flex justify-between items-start mb-2">
                        <div>
                            <label className="block text-xs font-bold text-slate-700 uppercase">{label}</label>
                            <span className="text-[10px] text-slate-500 block">Base Legal: <span className="font-mono font-bold text-blue-600">{format(calculatedBase)}</span></span>
                        </div>
                        {/* Toggle Switch */}
                        <div className="flex items-center gap-2 bg-white p-1 rounded-full border border-slate-200 no-print">
                             <button 
                                onClick={() => onToggleBase(false)}
                                className={`text-[10px] px-2 py-1 rounded-full transition-colors ${!usePdfBase ? 'bg-blue-100 text-blue-700 font-bold' : 'text-slate-400'}`}
                             >
                                Legal
                             </button>
                             <button 
                                onClick={() => onToggleBase(true)}
                                className={`text-[10px] px-2 py-1 rounded-full transition-colors ${usePdfBase ? 'bg-orange-100 text-orange-700 font-bold' : 'text-slate-400'}`}
                             >
                                PDF
                             </button>
                        </div>
                    </div>

                    <div className="relative">
                         <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span className="text-slate-500 sm:text-sm">$</span>
                        </div>
                        <input 
                            type="number"
                            value={value}
                            onChange={(e) => onChange(Number(e.target.value))}
                            className={`w-full p-2 pl-7 border rounded-lg focus:ring-2 outline-none transition-all text-sm ${value > 0 ? (isMatch ? 'bg-green-50 border-green-300 focus:ring-green-500' : 'bg-red-50 border-red-300 focus:ring-red-500') : 'bg-white border-slate-300 focus:ring-blue-500'}`}
                            placeholder="Ingrese valor base del PDF"
                        />
                         <div className="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            {value > 0 && (
                                isMatch 
                                ? <Icons.CheckCircle className="w-4 h-4 text-green-500" />
                                : <Icons.XCircle className="w-4 h-4 text-red-500" />
                            )}
                        </div>
                    </div>
                    
                    {usePdfBase && (
                        <div className="text-[10px] text-orange-600 mt-1 font-semibold flex items-center gap-1">
                            <Icons.AlertTriangle className="w-3 h-3" /> Usando base PDF para cálculos
                        </div>
                    )}
                    
                    {value > 0 && !isMatch && !usePdfBase && (
                        <div className="text-[10px] text-red-500 mt-1 text-right">
                            Difiere del legal por {format(Math.abs(calculatedBase - value))}
                        </div>
                    )}
                 </div>
             )
        }

        // --- APLICACIÓN PRINCIPAL ---

        function AuditApp() {
            // Helper para cargar de localStorage
            const loadNumber = (key, def) => Number(localStorage.getItem(key)) || def;
            const loadString = (key, def) => localStorage.getItem(key) || def;
            const loadBool = (key, def) => localStorage.getItem(key) === 'true' || def;

            // --- STATE: DATOS GENERALES ---
            const [salary, setSalary] = useState(() => loadNumber('audit_salary', 1423500));
            const [transportAid, setTransportAid] = useState(() => loadNumber('audit_transport', 200000));
            const [variableAvg, setVariableAvg] = useState(() => loadNumber('audit_variable', 0));
            
            // --- STATE: IMAGEN ANEXA ---
            const [headerImage, setHeaderImage] = useState(() => loadString('audit_headerImage', ''));

            // --- STATE: FECHAS Y TIEMPO ---
            const [startDate, setStartDate] = useState(() => loadString('audit_start', '2025-08-26'));
            const [endDate, setEndDate] = useState(() => loadString('audit_end', '2025-11-14'));
            const [suspensionDays, setSuspensionDays] = useState(() => loadNumber('audit_suspension', 0));
            const [permissionDays, setPermissionDays] = useState(() => loadNumber('audit_permission', 0));
            const [vacationDaysTaken, setVacationDaysTaken] = useState(() => loadNumber('audit_vacTaken', 0));

            // --- STATE: ULTIMO MES (SALARIO) ---
            const [lastMonthAbsenceDays, setLastMonthAbsenceDays] = useState(() => loadNumber('audit_lastMonthAbsence', 0)); // Días no laborados ultimo mes
            const [pdfLastMonthSalary, setPdfLastMonthSalary] = useState(() => loadNumber('audit_pdfLastSalary', 0));
            const [pdfLastMonthTransport, setPdfLastMonthTransport] = useState(() => loadNumber('audit_pdfLastTransport', 0));

            // --- STATE: SANCIONES ---
            const [sanctionDelayCount, setSanctionDelayCount] = useState(() => loadNumber('audit_sanctionDelay', 0));

            // --- STATE: BASES DEL PDF ---
            const [pdfBaseCesantias, setPdfBaseCesantias] = useState(() => loadNumber('audit_pdfBaseCesantias', 0));
            const [pdfBaseIntereses, setPdfBaseIntereses] = useState(() => loadNumber('audit_pdfBaseIntereses', 0));
            const [pdfBasePrima, setPdfBasePrima] = useState(() => loadNumber('audit_pdfBasePrima', 0));
            const [pdfBaseVacaciones, setPdfBaseVacaciones] = useState(() => loadNumber('audit_pdfBaseVacaciones', 0));

            // --- STATE: SELECCIÓN DE BASE (LEGAL vs PDF) ---
            const [usePdfCesantias, setUsePdfCesantias] = useState(() => loadBool('audit_usePdfCesantias', false));
            const [usePdfIntereses, setUsePdfIntereses] = useState(() => loadBool('audit_usePdfIntereses', false));
            const [usePdfPrima, setUsePdfPrima] = useState(() => loadBool('audit_usePdfPrima', false));
            const [usePdfVacaciones, setUsePdfVacaciones] = useState(() => loadBool('audit_usePdfVacaciones', false));

            // --- STATE: VALORES PRESTACIONES PDF ---
            const [docCesantias, setDocCesantias] = useState(() => loadNumber('audit_docCesantias', 0));
            const [docIntereses, setDocIntereses] = useState(() => loadNumber('audit_docIntereses', 0));
            const [docPrima, setDocPrima] = useState(() => loadNumber('audit_docPrima', 0));
            const [docVacaciones, setDocVacaciones] = useState(() => loadNumber('audit_docVacaciones', 0));
            
            // --- STATE: BONUS ---
            const [docBonus, setDocBonus] = useState(() => loadNumber('audit_docBonus', 0)); // NUEVO: Bonificaciones

            // --- STATE: DEDUCCIONES PDF ---
            const [pdfBaseSaludPension, setPdfBaseSaludPension] = useState(() => loadNumber('audit_pdfBaseSS', 0));
            const [docSalud, setDocSalud] = useState(() => loadNumber('audit_docSalud', 0));
            const [docPension, setDocPension] = useState(() => loadNumber('audit_docPension', 0));
            const [docLoans, setDocLoans] = useState(() => loadNumber('audit_docLoans', 0)); 

            // --- EFECTO: GUARDAR AUTOMÁTICAMENTE ---
            useEffect(() => {
                localStorage.setItem('audit_salary', salary);
                localStorage.setItem('audit_transport', transportAid);
                localStorage.setItem('audit_variable', variableAvg);
                localStorage.setItem('audit_headerImage', headerImage);
                localStorage.setItem('audit_start', startDate);
                localStorage.setItem('audit_end', endDate);
                
                localStorage.setItem('audit_pdfBaseCesantias', pdfBaseCesantias);
                localStorage.setItem('audit_pdfBaseIntereses', pdfBaseIntereses);
                localStorage.setItem('audit_pdfBasePrima', pdfBasePrima);
                localStorage.setItem('audit_pdfBaseVacaciones', pdfBaseVacaciones);

                localStorage.setItem('audit_usePdfCesantias', usePdfCesantias);
                localStorage.setItem('audit_usePdfIntereses', usePdfIntereses);
                localStorage.setItem('audit_usePdfPrima', usePdfPrima);
                localStorage.setItem('audit_usePdfVacaciones', usePdfVacaciones);

                localStorage.setItem('audit_docCesantias', docCesantias);
                localStorage.setItem('audit_docIntereses', docIntereses);
                localStorage.setItem('audit_docPrima', docPrima);
                localStorage.setItem('audit_docVacaciones', docVacaciones);
                
                localStorage.setItem('audit_suspension', suspensionDays);
                localStorage.setItem('audit_permission', permissionDays);
                localStorage.setItem('audit_vacTaken', vacationDaysTaken);
                
                localStorage.setItem('audit_lastMonthAbsence', lastMonthAbsenceDays);
                localStorage.setItem('audit_pdfLastSalary', pdfLastMonthSalary);
                localStorage.setItem('audit_pdfLastTransport', pdfLastMonthTransport);

                localStorage.setItem('audit_sanctionDelay', sanctionDelayCount);
                localStorage.setItem('audit_pdfBaseSS', pdfBaseSaludPension);
                localStorage.setItem('audit_docSalud', docSalud);
                localStorage.setItem('audit_docPension', docPension);
                localStorage.setItem('audit_docLoans', docLoans); 
                localStorage.setItem('audit_docBonus', docBonus); // Nuevo

            }, [salary, transportAid, variableAvg, headerImage, startDate, endDate, pdfBaseCesantias, pdfBaseIntereses, pdfBasePrima, pdfBaseVacaciones, usePdfCesantias, usePdfIntereses, usePdfPrima, usePdfVacaciones, docCesantias, docIntereses, docPrima, docVacaciones, suspensionDays, permissionDays, vacationDaysTaken, sanctionDelayCount, pdfBaseSaludPension, docSalud, docPension, docLoans, lastMonthAbsenceDays, pdfLastMonthSalary, pdfLastMonthTransport, docBonus]);

            // --- LOGICA DE CALCULO ---
            
            const calculateDays = (start, end) => {
                if (!start || !end) return 0;
                const s = new Date(start + 'T00:00:00'); 
                const e = new Date(end + 'T00:00:00');
                
                const yearDiff = e.getFullYear() - s.getFullYear();
                const monthDiff = (e.getMonth() - s.getMonth()) + (yearDiff * 12);
                
                let dayS = s.getDate();
                let dayE = e.getDate();
                
                if (dayS === 31) dayS = 30;
                if (dayE === 31) dayE = 30;
                
                return Math.max(0, (monthDiff * 30) + (dayE - dayS) + 1);
            };

            const totalDays = calculateDays(startDate, endDate) - suspensionDays - permissionDays;
            
            // --- CÁLCULO DÍAS ÚLTIMO MES (SALARIO) ---
            const getFinalMonthDays = () => {
                if (!startDate || !endDate) return 0;
                const s = new Date(startDate + 'T00:00:00');
                const e = new Date(endDate + 'T00:00:00');
                
                // Si la fecha de inicio es en el mismo mes y año que la de fin, se restan
                if (s.getFullYear() === e.getFullYear() && s.getMonth() === e.getMonth()) {
                     let dayS = s.getDate();
                     let dayE = e.getDate();
                     if (dayE === 31) dayE = 30;
                     if (dayS === 31) dayS = 30;
                     return Math.max(0, (dayE - dayS) + 1);
                } else {
                    // Si no, son los días del mes de terminación (día comercial 30)
                    let dayE = e.getDate();
                    if (dayE === 31) dayE = 30;
                    return dayE;
                }
            };

            const daysInFinalMonth = getFinalMonthDays();
            const daysToPayFinalMonth = Math.max(0, daysInFinalMonth - lastMonthAbsenceDays);
            
            const valFinalMonthSalary = Math.round((parseFloat(salary) / 30) * daysToPayFinalMonth);
            const valFinalMonthTransport = Math.round((parseFloat(transportAid) / 30) * daysToPayFinalMonth);

            // --- GENERAR MES A MES (NUEVO) ---
            const monthlyBreakdown = useMemo(() => {
                if (!startDate || !endDate) return [];
                const s = new Date(startDate + 'T00:00:00');
                const e = new Date(endDate + 'T00:00:00');
                const rows = [];
                
                let current = new Date(s.getFullYear(), s.getMonth(), 1);
                // Fecha limite es el primer dia del mes siguiente a la fecha fin
                const limit = new Date(e.getFullYear(), e.getMonth() + 1, 1);
                
                while(current < limit) {
                    const year = current.getFullYear();
                    const month = current.getMonth();
                    
                    let daysInMonth = 30;
                    
                    // Si es el mes de inicio
                    if (year === s.getFullYear() && month === s.getMonth()) {
                        let d = s.getDate();
                        if (d === 31) d = 30;
                        daysInMonth = 30 - d + 1;
                    }
                    // Si es el mes de fin
                    else if (year === e.getFullYear() && month === e.getMonth()) {
                         let d = e.getDate();
                         if (d === 31) d = 30;
                         daysInMonth = d;
                    }

                    // Ajuste caso especial: Inicio y Fin en el mismo mes
                    if (s.getFullYear() === e.getFullYear() && s.getMonth() === e.getMonth()) {
                         let d1 = s.getDate();
                         let d2 = e.getDate();
                         if (d1 === 31) d1 = 30;
                         if (d2 === 31) d2 = 30;
                         daysInMonth = d2 - d1 + 1;
                    }

                    const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                    
                    rows.push({
                        id: `${year}-${month}`,
                        name: `${monthNames[month]} ${year}`,
                        days: daysInMonth
                    });
                    
                    current.setMonth(current.getMonth() + 1);
                }
                return rows;
            }, [startDate, endDate]);

            // Suma bruta del breakdown
            const totalGrossDays = monthlyBreakdown.reduce((acc, curr) => acc + curr.days, 0);


            // --- PRESTACIONES ---
            const daysCesantias = totalDays; 
            const daysPrima = totalDays; 

            // Bases Legales (Calculadas)
            const basePrestacionesLegal = parseFloat(salary) + parseFloat(transportAid) + parseFloat(variableAvg);
            const baseVacacionesLegal = parseFloat(salary) + parseFloat(variableAvg); 

            // Bases Efectivas (Seleccionadas)
            const finalBaseCesantias = usePdfCesantias && pdfBaseCesantias > 0 ? pdfBaseCesantias : basePrestacionesLegal;
            const finalBaseIntereses = usePdfIntereses && pdfBaseIntereses > 0 ? pdfBaseIntereses : basePrestacionesLegal;
            const finalBasePrima = usePdfPrima && pdfBasePrima > 0 ? pdfBasePrima : basePrestacionesLegal;
            const finalBaseVacaciones = usePdfVacaciones && pdfBaseVacaciones > 0 ? pdfBaseVacaciones : baseVacacionesLegal;

            const valCesantias = Math.round((finalBaseCesantias * daysCesantias) / 360);
            
            const baseParaCalculoIntereses = usePdfIntereses && pdfBaseIntereses > 0 ? pdfBaseIntereses : valCesantias;
            const valIntereses = Math.round((baseParaCalculoIntereses * daysCesantias * 0.12) / 360);
            
            const valPrima = Math.round((finalBasePrima * daysPrima) / 360);
            
            const vacationDaysAccrued = (totalDays * 15) / 360;
            const vacationDaysPending = vacationDaysAccrued - vacationDaysTaken;
            const valVacaciones = Math.round((finalBaseVacaciones * vacationDaysPending) / 30); 

            // Calculo de Deducciones
            const valorDia = parseFloat(salary) / 30;
            const valSancionRetardo = Math.round((valorDia / 5) * sanctionDelayCount);

            const baseSSCalculada = pdfBaseSaludPension > 0 ? pdfBaseSaludPension : (parseFloat(salary) + parseFloat(variableAvg)); 
            const valSalud = Math.round(baseSSCalculada * 0.04);
            const valPension = Math.round(baseSSCalculada * 0.04);

            // TOTALES FINALES
            // Incluimos docBonus (Bonificaciones) como ingreso
            const totalDevengosCalculado = Number(valCesantias) + Number(valIntereses) + Number(valPrima) + Number(valVacaciones) + Number(valFinalMonthSalary) + Number(valFinalMonthTransport) + Number(docBonus);
            const totalDeduccionesCalculado = Number(valSalud) + Number(valPension) + Number(valSancionRetardo) + Number(docLoans);
            const totalNetoPagar = totalDevengosCalculado - totalDeduccionesCalculado;

            const handleImageUpload = (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onloadend = () => {
                        setHeaderImage(reader.result);
                    };
                    reader.readAsDataURL(file);
                }
            };

            const handlePrint = () => {
                window.print();
            };

            return (
                <div className="p-4 print:p-0">
                    <header className="max-w-5xl mx-auto mb-6 flex flex-col md:flex-row justify-between items-center print:mb-4 gap-4">
                        <div className="flex items-center gap-4">
                             {/* Anexo Imagen - Visualización */}
                            {headerImage ? (
                                <div className="relative group">
                                    <img src={headerImage} alt="Logo Anexo" className="h-16 w-auto object-contain rounded-md border border-slate-200" />
                                    <button 
                                        onClick={() => setHeaderImage('')} 
                                        className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity no-print"
                                        title="Quitar imagen"
                                    >
                                        <Icons.XCircle className="w-3 h-3" />
                                    </button>
                                </div>
                            ) : (
                                <div className="no-print">
                                    <label className="flex flex-col items-center justify-center w-16 h-16 border-2 border-slate-300 border-dashed rounded-lg cursor-pointer hover:bg-slate-50">
                                        <Icons.Image className="w-6 h-6 text-slate-400" />
                                        <span className="text-[8px] text-slate-500 mt-1">Logo/Img</span>
                                        <input type="file" className="hidden" accept="image/*" onChange={handleImageUpload} />
                                    </label>
                                </div>
                            )}
                            
                            <div>
                                <h1 className="text-3xl font-extrabold text-blue-900 flex items-center gap-3">
                                    <Icons.Calculator className="w-8 h-8" />
                                    Auditor de Liquidaciones
                                </h1>
                                <p className="text-slate-600 mt-1 print:text-xs">Herramienta de verificación de cálculos laborales Colombia 2025</p>
                            </div>
                        </div>
                        <button 
                            onClick={handlePrint}
                            className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors shadow-md no-print"
                        >
                            <Icons.Printer className="w-4 h-4" />
                            Imprimir / Guardar PDF
                        </button>
                    </header>

                    <div className="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6">
                        
                        {/* COLUMNA IZQUIERDA: INPUTS */}
                        <div className="lg:col-span-4 space-y-6 no-print">
                            <Card className="p-5">
                                <SectionTitle icon="FileText" title="1. Datos del Contrato" />
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="col-span-2">
                                        <InputGroup label="Fecha Inicio" type="date" value={startDate} onChange={setStartDate} />
                                    </div>
                                    <div className="col-span-2">
                                        <InputGroup label="Fecha Terminación" type="date" value={endDate} onChange={setEndDate} />
                                    </div>
                                    <InputGroup label="Días Suspensión" type="number" value={suspensionDays} onChange={setSuspensionDays} />
                                    <InputGroup label="Días Permisos/Lic" type="number" value={permissionDays} onChange={setPermissionDays} />
                                    <div className="col-span-2">
                                        <InputGroup label="Días Vac. Disfrutados" type="number" value={vacationDaysTaken} onChange={setVacationDaysTaken} />
                                    </div>
                                </div>
                                
                                <div className="bg-blue-50 p-3 rounded mt-2 border border-blue-100">
                                    <div className="flex justify-between items-center">
                                        <span className="text-xs font-bold text-blue-800 uppercase">Días a Liquidar</span>
                                        <span className="text-xl font-bold text-blue-900">{totalDays}</span>
                                    </div>
                                </div>
                            </Card>

                            <Card className="p-5">
                                <SectionTitle icon="DollarSign" title="2. Bases Salariales" />
                                <InputGroup label="Sueldo Básico" type="number" prefix="$" value={salary} onChange={setSalary} />
                                <InputGroup label="Auxilio de Transporte" type="number" prefix="$" value={transportAid} onChange={setTransportAid} />
                                <InputGroup label="Promedio Variable" type="number" prefix="$" value={variableAvg} onChange={setVariableAvg} />
                            </Card>

                            {/* SECCIÓN: BASES DEL PDF CON SELECTOR */}
                            <Card className="p-5 border-l-4 border-l-orange-400">
                                <SectionTitle icon="Scale" title="3. Bases Usadas (Selector)" />
                                <p className="text-xs text-slate-500 mb-4">Ingresa la base del PDF. Usa el interruptor para decidir con qué base calcular la auditoría final.</p>
                                
                                <BaseInput 
                                    label="Base Cesantías" 
                                    calculatedBase={basePrestacionesLegal} 
                                    value={pdfBaseCesantias} 
                                    onChange={setPdfBaseCesantias}
                                    usePdfBase={usePdfCesantias}
                                    onToggleBase={setUsePdfCesantias}
                                />
                                <BaseInput 
                                    label="Base Valor Cesantías (Para Intereses)" 
                                    calculatedBase={valCesantias} 
                                    value={pdfBaseIntereses} 
                                    onChange={setPdfBaseIntereses}
                                    usePdfBase={usePdfIntereses}
                                    onToggleBase={setUsePdfIntereses}
                                />
                                <BaseInput 
                                    label="Base Prima" 
                                    calculatedBase={basePrestacionesLegal} 
                                    value={pdfBasePrima} 
                                    onChange={setPdfBasePrima}
                                    usePdfBase={usePdfPrima}
                                    onToggleBase={setUsePdfPrima}
                                />
                                <BaseInput 
                                    label="Base Vacaciones" 
                                    calculatedBase={baseVacacionesLegal} 
                                    value={pdfBaseVacaciones} 
                                    onChange={setPdfBaseVacaciones}
                                    usePdfBase={usePdfVacaciones}
                                    onToggleBase={setUsePdfVacaciones}
                                />
                            </Card>

                            {/* NUEVA SECCIÓN: SALARIO ÚLTIMO MES */}
                            <Card className="p-5 border-l-4 border-l-teal-500">
                                <SectionTitle icon="DollarSign" title="4. Salario y Auxilio (Último Mes)" />
                                <p className="text-xs text-slate-500 mb-3">Calcula el sueldo desde el día 1 del mes de retiro hasta la fecha de terminación.</p>
                                
                                <div className="mb-4 bg-teal-50 p-3 rounded-lg border border-teal-100">
                                    <div className="flex justify-between items-center mb-1">
                                        <span className="text-xs font-bold text-teal-800 uppercase">Días Calendario Mes Fin</span>
                                        <span className="font-bold text-teal-900">{daysInFinalMonth}</span>
                                    </div>
                                    <div className="flex items-center justify-between gap-2 mb-2">
                                        <span className="text-xs text-slate-600">(-) Días no laborados/Hábiles:</span>
                                        <input 
                                            type="number" 
                                            value={lastMonthAbsenceDays} 
                                            onChange={(e) => setLastMonthAbsenceDays(Number(e.target.value))}
                                            className="w-16 p-1 text-right text-xs border rounded"
                                        />
                                    </div>
                                    <div className="flex justify-between items-center pt-2 border-t border-teal-200">
                                        <span className="text-xs font-bold text-teal-800 uppercase">Total Días a Pagar</span>
                                        <span className="font-bold text-xl text-teal-900">{daysToPayFinalMonth}</span>
                                    </div>
                                </div>

                                <InputGroup label="Sueldo Último Mes (PDF)" type="number" prefix="$" value={pdfLastMonthSalary} onChange={setPdfLastMonthSalary} />
                                <InputGroup label="Aux. Transp Último Mes (PDF)" type="number" prefix="$" value={pdfLastMonthTransport} onChange={setPdfLastMonthTransport} />
                            </Card>

                            <Card className="p-5 border-l-4 border-l-purple-500">
                                <SectionTitle icon="FileText" title="5. Valores Totales (PDF)" />
                                <p className="text-xs text-slate-500 mb-4">Ingresa aquí los totales finales (devengos) que aparecen en la liquidación.</p>
                                <InputGroup label="Total Cesantías (PDF)" type="number" prefix="$" value={docCesantias} onChange={setDocCesantias} />
                                <InputGroup label="Total Intereses (PDF)" type="number" prefix="$" value={docIntereses} onChange={setDocIntereses} />
                                <InputGroup label="Total Prima (PDF)" type="number" prefix="$" value={docPrima} onChange={setDocPrima} />
                                <InputGroup label="Total Vacaciones (PDF)" type="number" prefix="$" value={docVacaciones} onChange={setDocVacaciones} />
                                <div className="mt-4 pt-4 border-t border-slate-200">
                                    <label className="block text-xs font-bold text-green-700 uppercase mb-1">Bonificaciones (No Salariales)</label>
                                    <InputGroup type="number" prefix="$" value={docBonus} onChange={setDocBonus} placeholder="Opcional" />
                                </div>
                            </Card>

                             {/* SECCIÓN: DEDUCCIONES MANUALES */}
                             <Card className="p-5 border-l-4 border-l-red-500">
                                <SectionTitle icon="MinusCircle" title="6. Deducciones (PDF)" />
                                <p className="text-xs text-slate-500 mb-2">Ingresa los descuentos aplicados.</p>
                                
                                <div className="mb-4 p-2 bg-slate-50 rounded border border-slate-200">
                                    <label className="block text-xs font-semibold text-slate-500 uppercase mb-1">Base Seguridad Social (PDF)</label>
                                    <input type="number" value={pdfBaseSaludPension} onChange={(e) => setPdfBaseSaludPension(Number(e.target.value))} className="w-full p-1 border rounded text-sm" placeholder="Ej: Devengado del mes" />
                                </div>

                                <InputGroup label="Descuento Salud (PDF)" type="number" prefix="$" value={docSalud} onChange={setDocSalud} />
                                <InputGroup label="Descuento Pensión (PDF)" type="number" prefix="$" value={docPension} onChange={setDocPension} />
                                <InputGroup label="Otras Deducciones (Préstamos/Anticipos)" type="number" prefix="$" value={docLoans} onChange={setDocLoans} />
                                
                                <div className="mt-4 pt-4 border-t border-slate-200">
                                    <label className="block text-xs font-semibold text-slate-500 uppercase mb-1">Sanciones por Retardo</label>
                                    <div className="flex items-center gap-2 mb-2">
                                        <input 
                                            type="number" 
                                            value={sanctionDelayCount} 
                                            onChange={(e) => setSanctionDelayCount(Number(e.target.value))}
                                            className="w-16 p-2 border rounded text-center"
                                        />
                                        <span className="text-sm text-slate-600">veces (1/5 del día)</span>
                                    </div>
                                    <div className="text-xs text-red-600 font-bold">
                                        Deducción calc: {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(valSancionRetardo)}
                                    </div>
                                </div>
                            </Card>
                        </div>

                        {/* COLUMNA DERECHA: RESULTADOS */}
                        <div className="lg:col-span-8 space-y-6 print:col-span-12">
                            
                            {/* VISIBLE SOLO EN IMPRESION */}
                            <div className="hidden print:block mb-4 p-4 border border-slate-300 rounded-lg">
                                <h3 className="font-bold mb-2">Parámetros de la Auditoría</h3>
                                <div className="grid grid-cols-2 text-sm gap-2">
                                    <div>Fecha Inicio: {startDate}</div>
                                    <div>Fecha Fin: {endDate}</div>
                                    <div>Días Laborados Total: {totalDays}</div>
                                    <div>Días a Pagar Último Mes: {daysToPayFinalMonth}</div>
                                    <div>Base Prestacional Legal: {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(basePrestacionesLegal)}</div>
                                </div>
                            </div>

                            <Card className="p-6 print:shadow-none print:border-none">
                                <SectionTitle icon="AlertTriangle" title="Resultado de la Auditoría" />
                                
                                <div className="space-y-4">
                                    
                                    {/* NUEVO BLOQUE: RESULTADOS SALARIO ÚLTIMO MES */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 pb-4 border-b border-slate-200">
                                        <div className="bg-teal-50 p-4 rounded-lg border border-teal-200 print:bg-white print:border-slate-300">
                                            <h4 className="font-bold text-slate-700 mb-2 flex items-center gap-2">
                                                <Icons.DollarSign className="w-4 h-4 text-teal-600"/> Sueldo ({daysToPayFinalMonth} días)
                                            </h4>
                                            <div className="text-xs text-slate-500 mb-2 font-mono">
                                                Formula: (Basico / 30) * {daysToPayFinalMonth}
                                            </div>
                                            <ResultRow label="Valor Neto" calculated={valFinalMonthSalary} document={pdfLastMonthSalary} />
                                        </div>
                                        <div className="bg-teal-50 p-4 rounded-lg border border-teal-200 print:bg-white print:border-slate-300">
                                            <h4 className="font-bold text-slate-700 mb-2 flex items-center gap-2">
                                                <Icons.DollarSign className="w-4 h-4 text-teal-600"/> Aux. Transp ({daysToPayFinalMonth} días)
                                            </h4>
                                            <div className="text-xs text-slate-500 mb-2 font-mono">
                                                Formula: (Aux / 30) * {daysToPayFinalMonth}
                                            </div>
                                            <ResultRow label="Valor Neto" calculated={valFinalMonthTransport} document={pdfLastMonthTransport} />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 print:grid-cols-2">
                                        {/* Cesantias */}
                                        <div className="bg-slate-50 p-4 rounded-lg border border-slate-200 print:bg-white print:border-slate-300">
                                            <h4 className="font-bold text-slate-700 mb-2 flex items-center gap-2">
                                                <Icons.Clock className="w-4 h-4 text-orange-500"/> Cesantías
                                            </h4>
                                            <div className="text-xs text-slate-500 mb-2 font-mono">
                                                Base usada: {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(finalBaseCesantias)}
                                            </div>
                                            <div className="text-xs text-slate-400 mb-1">
                                                Formula: (Base * {daysCesantias}) / 360
                                            </div>
                                            <ResultRow label="Valor Neto" calculated={valCesantias} document={docCesantias} />
                                        </div>

                                        {/* Intereses */}
                                        <div className="bg-slate-50 p-4 rounded-lg border border-slate-200 print:bg-white print:border-slate-300">
                                            <h4 className="font-bold text-slate-700 mb-2 flex items-center gap-2">
                                                <Icons.Clock className="w-4 h-4 text-orange-500"/> Intereses (12%)
                                            </h4>
                                            <div className="text-xs text-slate-500 mb-2 font-mono">
                                                Base Cesantías: {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(baseParaCalculoIntereses)}
                                            </div>
                                            <div className="text-xs text-slate-400 mb-1">
                                                Formula: (Cesantias * {daysCesantias} * 0.12) / 360
                                            </div>
                                            <ResultRow label="Valor Neto" calculated={valIntereses} document={docIntereses} />
                                        </div>

                                        {/* Prima */}
                                        <div className="bg-slate-50 p-4 rounded-lg border border-slate-200 print:bg-white print:border-slate-300">
                                            <h4 className="font-bold text-slate-700 mb-2 flex items-center gap-2">
                                                <Icons.Calendar className="w-4 h-4 text-blue-500"/> Prima de Servicios
                                            </h4>
                                            <div className="text-xs text-slate-500 mb-2 font-mono">
                                                 Base usada: {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(finalBasePrima)}
                                            </div>
                                            <div className="text-xs text-slate-400 mb-1">
                                                Formula: (Base * {daysPrima}) / 360
                                            </div>
                                            <ResultRow label="Valor Neto" calculated={valPrima} document={docPrima} />
                                        </div>

                                        {/* Vacaciones */}
                                        <div className="bg-slate-50 p-4 rounded-lg border border-slate-200 print:bg-white print:border-slate-300">
                                            <h4 className="font-bold text-slate-700 mb-2 flex items-center gap-2">
                                                <Icons.Calendar className="w-4 h-4 text-green-500"/> Vacaciones
                                            </h4>
                                            <div className="text-xs text-slate-500 mb-2 font-mono">
                                                 Base usada: {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP' }).format(finalBaseVacaciones)}
                                            </div>
                                            <div className="flex justify-between text-xs mb-1">
                                                <span>Días Generados:</span>
                                                <span className="font-bold">{vacationDaysAccrued.toFixed(2)}</span>
                                            </div>
                                            <div className="flex justify-between text-xs mb-2 pb-2 border-b border-slate-200">
                                                <span>Días a Pagar:</span>
                                                <span className="font-bold">{vacationDaysPending.toFixed(2)}</span>
                                            </div>
                                            <ResultRow label="Valor Neto" calculated={valVacaciones} document={docVacaciones} />
                                        </div>
                                    </div>

                                    {/* SECCIÓN DEDUCCIONES RESULTADO */}
                                    <div className="mt-6 pt-4 border-t border-slate-200">
                                        <h4 className="font-bold text-slate-700 mb-3 flex items-center gap-2">
                                            <Icons.MinusCircle className="w-4 h-4 text-red-500"/> Deducciones de Nómina/Liq.
                                        </h4>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 print:grid-cols-2">
                                            <div className="bg-red-50 p-3 rounded-lg border border-red-100">
                                                <ResultRow label="Salud (4%)" calculated={valSalud} document={docSalud} isCurrency={true} />
                                            </div>
                                            <div className="bg-red-50 p-3 rounded-lg border border-red-100">
                                                <ResultRow label="Pensión (4%)" calculated={valPension} document={docPension} isCurrency={true} />
                                            </div>
                                        </div>
                                        
                                        {(sanctionDelayCount > 0 || docLoans > 0) && (
                                            <div className="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                                                {sanctionDelayCount > 0 && (
                                                    <div className="bg-red-50 p-3 rounded-lg border border-red-100">
                                                        <div className="flex justify-between items-center text-sm">
                                                            <span className="font-medium text-slate-700">Sanción Retardos ({sanctionDelayCount})</span>
                                                            <span className="font-bold text-red-600">
                                                                - {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(valSancionRetardo)}
                                                            </span>
                                                        </div>
                                                        <div className="text-[10px] text-slate-500 mt-1">
                                                            Cálculo: (Sueldo Diario / 5) * {sanctionDelayCount}
                                                        </div>
                                                    </div>
                                                )}
                                                {docLoans > 0 && (
                                                    <div className="bg-red-50 p-3 rounded-lg border border-red-100">
                                                        <div className="flex justify-between items-center text-sm">
                                                            <span className="font-medium text-slate-700">Préstamos / Anticipos</span>
                                                            <span className="font-bold text-red-600">
                                                                - {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(docLoans)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* TOTALES FINALES CON SUBTOTALES */}
                                <div className="mt-6 pt-4 border-t border-slate-200 bg-slate-50 p-4 rounded-lg">
                                    <div className="flex justify-between items-center mb-2 text-sm">
                                        <span className="text-slate-600 font-medium">Subtotal Devengos (Prestaciones + Salario)</span>
                                        <span className="font-bold text-slate-800 text-lg">
                                            {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(totalDevengosCalculado)}
                                        </span>
                                    </div>
                                    {/* Muestra bonificaciones sumadas si existen */}
                                    {docBonus > 0 && (
                                        <div className="flex justify-between items-center mb-2 text-sm text-green-700">
                                            <span className="font-medium flex items-center gap-1"><Icons.Gift className="w-4 h-4"/> (+) Bonificaciones Extra</span>
                                            <span className="font-bold text-lg">
                                                {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(docBonus)}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between items-center mb-4 text-sm">
                                        <span className="text-slate-600 font-medium">Subtotal Deducciones</span>
                                        <span className="font-bold text-red-600 text-lg">
                                            - {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(totalDeduccionesCalculado)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between items-center pt-2 border-t border-slate-300">
                                        <span className="text-base font-black text-slate-900 uppercase">Total Neto a Pagar</span>
                                        <div className="text-right">
                                            <div className="text-2xl font-black text-blue-900">
                                                {new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(totalNetoPagar)}
                                            </div>
                                            <div className="text-[10px] text-slate-400 font-medium mt-1">
                                                (Devengos + Bonificaciones - Deducciones)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                            
                            <Card className="p-6 bg-yellow-50 border-yellow-200 print:border-none print:shadow-none">
                                <h3 className="font-bold text-yellow-800 flex items-center gap-2 mb-2">
                                    <Icons.AlertTriangle className="w-5 h-5" /> Notas de Auditoría
                                </h3>
                                <ul className="list-disc list-inside text-sm text-yellow-900 space-y-1">
                                    <li><strong>Sueldo Último Mes:</strong> Se calcula automáticamente desde el día 1 del mes de retiro hasta la fecha fin. Resta manualmente días no laborados si aplica.</li>
                                    <li><strong>Selector de Base:</strong> Si activas "PDF" en la sección 3, los cálculos de arriba usarán esa base errónea para verificar si al menos la aritmética interna es consistente.</li>
                                    <li><strong>Permisos y Suspensiones:</strong> Ambos se restan del total de días laborados para el cálculo de prestaciones.</li>
                                    <li><strong>Préstamos:</strong> Verifique siempre que exista autorización firmada para los descuentos por préstamos o anticipos.</li>
                                </ul>
                            </Card>
                        </div>
                    </div>
                    
                    {/* TABLAS DETALLADAS (NO IMPRIMIBLES) */}
                    <div className="max-w-5xl mx-auto mt-8 no-print">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            {/* TABLA DÍAS LABORADOS */}
                            <Card className="p-5 border-t-4 border-t-blue-500">
                                <SectionTitle icon="Calendar" title="Detalle Días Laborados" />
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm text-left">
                                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                            <tr>
                                                <th className="px-3 py-2">Mes</th>
                                                <th className="px-3 py-2 text-right">Días (30)</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {monthlyBreakdown.map((row) => (
                                                <tr key={row.id}>
                                                    <td className="px-3 py-2">{row.name}</td>
                                                    <td className="px-3 py-2 text-right font-medium">{row.days}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot className="bg-slate-50 border-t border-slate-200">
                                            <tr>
                                                <td className="px-3 py-2 font-bold text-slate-700">Total Bruto</td>
                                                <td className="px-3 py-2 text-right font-bold text-blue-600">{totalGrossDays}</td>
                                            </tr>
                                            <tr>
                                                <td className="px-3 py-2 text-red-600">(-) Suspensiones/Permisos</td>
                                                <td className="px-3 py-2 text-right text-red-600 font-medium">-{suspensionDays + permissionDays}</td>
                                            </tr>
                                            <tr>
                                                <td className="px-3 py-2 font-black text-slate-800">Total Neto Liquidación</td>
                                                <td className="px-3 py-2 text-right font-black text-slate-800">{totalGrossDays - suspensionDays - permissionDays}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <p className="text-[10px] text-slate-400 mt-2">* Cálculo basado en mes comercial de 30 días.</p>
                            </Card>

                            {/* TABLA VACACIONES */}
                            <Card className="p-5 border-t-4 border-t-green-500">
                                <SectionTitle icon="Calendar" title="Detalle Vacaciones" />
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm text-left">
                                        <thead className="bg-slate-50 text-slate-500 uppercase text-xs">
                                            <tr>
                                                <th className="px-3 py-2">Mes</th>
                                                <th className="px-3 py-2 text-right">Días Base</th>
                                                <th className="px-3 py-2 text-right">Generadas</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {monthlyBreakdown.map((row) => {
                                                const accrued = (row.days * 15) / 360;
                                                return (
                                                    <tr key={row.id}>
                                                        <td className="px-3 py-2">{row.name}</td>
                                                        <td className="px-3 py-2 text-right text-slate-500">{row.days}</td>
                                                        <td className="px-3 py-2 text-right font-medium text-green-700">{accrued.toFixed(4)}</td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                        <tfoot className="bg-slate-50 border-t border-slate-200">
                                            <tr>
                                                <td className="px-3 py-2 font-bold text-slate-700">Total Generado</td>
                                                <td className="px-3 py-2 text-right text-slate-500">{totalGrossDays}</td>
                                                <td className="px-3 py-2 text-right font-bold text-green-600">
                                                    {((totalGrossDays * 15) / 360).toFixed(2)}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colSpan="2" className="px-3 py-2 text-red-600">(-) Afectación por Susp. ({(suspensionDays)} días)</td>
                                                <td className="px-3 py-2 text-right text-red-600 font-medium">
                                                    -{((suspensionDays * 15) / 360).toFixed(2)}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colSpan="2" className="px-3 py-2 font-black text-slate-800">Total Neto Acumulado</td>
                                                <td className="px-3 py-2 text-right font-black text-slate-800">
                                                    {vacationDaysAccrued.toFixed(2)}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <p className="text-[10px] text-slate-400 mt-2">* Fórmula: (Días * 15) / 360. Suspensiones no acumulan vacaciones.</p>
                            </Card>

                        </div>
                    </div>

                </div>
            );
        }

        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<AuditApp />);
    </script>
</body>
</html>