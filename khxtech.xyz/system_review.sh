#!/bin/bash

# 🔍 Hook شامل لمراجعة النظام واقتراح التحسينات
# يُستخدم كـ post-request hook أو يمكن تشغيله يدوياً

set -e

# ألوان للطباعة
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

# مسارات ومتغيرات
PROJECT_ROOT=${CLAUDE_PROJECT_PATH:-$(pwd)}
REPORT_DIR="$PROJECT_ROOT/.system-review"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_FILE="$REPORT_DIR/system_review_$TIMESTAMP.md"

# إنشاء مجلد التقارير
mkdir -p "$REPORT_DIR"

echo -e "${BLUE}🔍 بدء المراجعة الشاملة للنظام...${NC}"
echo "📁 مسار المشروع: $PROJECT_ROOT"
echo "📄 ملف التقرير: $REPORT_FILE"
echo ""

# بدء كتابة التقرير
cat > "$REPORT_FILE" << EOF
# 🔍 تقرير المراجعة الشاملة للنظام

**التاريخ:** $(date)  
**المشروع:** $(basename "$PROJECT_ROOT")  
**المسار:** $PROJECT_ROOT

---

EOF

# ===========================================
# 1. تحليل بنية المشروع
# ===========================================
echo -e "${CYAN}📊 1. تحليل بنية المشروع...${NC}"

{
    echo "## 📊 تحليل بنية المشروع"
    echo ""
    
    # إحصائيات الملفات
    total_files=$(find "$PROJECT_ROOT" -type f | wc -l)
    code_files=$(find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" -o -name "*.jsx" -o -name "*.tsx" -o -name "*.html" -o -name "*.css" -o -name "*.php" -o -name "*.py" | wc -l)
    
    echo "### 📈 إحصائيات عامة:"
    echo "- **إجمالي الملفات:** $total_files"
    echo "- **ملفات الكود:** $code_files"
    echo "- **حجم المشروع:** $(du -sh "$PROJECT_ROOT" | cut -f1)"
    echo ""
    
    # بنية المجلدات
    echo "### 📁 بنية المجلدات:"
    echo '```'
    tree "$PROJECT_ROOT" -d -L 3 2>/dev/null || find "$PROJECT_ROOT" -type d | head -20
    echo '```'
    echo ""
    
} >> "$REPORT_FILE"

# ===========================================
# 2. كشف التكرار في الكود
# ===========================================
echo -e "${YELLOW}🔄 2. كشف التكرار في الكود...${NC}"

{
    echo "## 🔄 تحليل التكرار والعمليات المكررة"
    echo ""
    
    echo "### 🚨 ملفات محتملة للتكرار:"
    
    # البحث عن الدوال والفئات المكررة
    echo "#### JavaScript/TypeScript:"
    find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" -o -name "*.jsx" -o -name "*.tsx" | while read file; do
        if [ -f "$file" ]; then
            # البحث عن الدوال المكررة
            grep -n "function\|const.*=.*=>\|class\|export.*function" "$file" 2>/dev/null | \
            sed 's/^/  /' | head -5
        fi
    done | sort | uniq -c | sort -rn | head -10 | while read count line; do
        if [ "$count" -gt 1 ]; then
            echo "- **$count مرات:** $line"
        fi
    done
    
    echo ""
    echo "#### HTML المكرر:"
    find "$PROJECT_ROOT" -name "*.html" | while read file; do
        if [ -f "$file" ]; then
            # البحث عن العناصر المكررة
            grep -o '<[^>]*class="[^"]*"[^>]*>' "$file" 2>/dev/null
        fi
    done | sort | uniq -c | sort -rn | head -5 | while read count line; do
        if [ "$count" -gt 3 ]; then
            echo "- **$count مرات:** $line"
        fi
    done
    
    echo ""
    echo "#### CSS المكرر:"
    find "$PROJECT_ROOT" -name "*.css" | while read file; do
        if [ -f "$file" ]; then
            # البحث عن القواعد المكررة
            grep -o '[^{]*{[^}]*}' "$file" 2>/dev/null | head -10
        fi
    done | sort | uniq -c | sort -rn | head -5 | while read count line; do
        if [ "$count" -gt 2 ]; then
            echo "- **$count مرات:** $line"
        fi
    done
    
    echo ""
    
} >> "$REPORT_FILE"

# ===========================================
# 3. تحليل جودة الكود
# ===========================================
echo -e "${PURPLE}⭐ 3. تحليل جودة الكود...${NC}"

{
    echo "## ⭐ تحليل جودة الكود"
    echo ""
    
    # تحليل تعقد الكود
    echo "### 🧮 تحليل التعقد:"
    find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" | while read file; do
        if [ -f "$file" ]; then
            lines=$(wc -l < "$file")
            functions=$(grep -c "function\|=>" "$file" 2>/dev/null || echo 0)
            if [ "$lines" -gt 200 ] || [ "$functions" -gt 20 ]; then
                echo "- **⚠️ ملف معقد:** $(basename "$file") - $lines سطر، $functions دالة"
            fi
        fi
    done
    
    echo ""
    echo "### 🎯 مؤشرات الجودة:"
    
    # حساب متوسط طول الدوال
    total_functions=0
    total_lines=0
    find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" | while read file; do
        if [ -f "$file" ]; then
            func_count=$(grep -c "function\|=>" "$file" 2>/dev/null || echo 0)
            line_count=$(wc -l < "$file")
            total_functions=$((total_functions + func_count))
            total_lines=$((total_lines + line_count))
        fi
    done
    
    echo "- **متوسط الأسطر لكل ملف:** تقريباً $(find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" | xargs wc -l 2>/dev/null | tail -1 | awk '{print int($1/NR)}')"
    echo "- **الملفات الكبيرة (+200 سطر):** $(find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" -exec wc -l {} \; | awk '$1 > 200 {count++} END {print count+0}')"
    
    echo ""
    
} >> "$REPORT_FILE"

# ===========================================
# 4. كشف العمليات غير المفيدة
# ===========================================
echo -e "${RED}🗑️ 4. كشف العمليات غير المفيدة...${NC}"

{
    echo "## 🗑️ العمليات غير المفيدة والميتة"
    echo ""
    
    echo "### 💀 كود ميت محتمل:"
    
    # البحث عن المتغيرات غير المستخدمة
    find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" | while read file; do
        if [ -f "$file" ]; then
            # استخراج تعريفات المتغيرات
            grep -n "let\|const\|var" "$file" 2>/dev/null | while read line; do
                var_name=$(echo "$line" | sed 's/.*\(let\|const\|var\) *\([a-zA-Z_][a-zA-Z0-9_]*\).*/\2/')
                if [ -n "$var_name" ] && [ "$var_name" != "let" ] && [ "$var_name" != "const" ] && [ "$var_name" != "var" ]; then
                    # البحث عن استخدام المتغير
                    usage_count=$(grep -c "$var_name" "$file" 2>/dev/null || echo 0)
                    if [ "$usage_count" -eq 1 ]; then
                        echo "- **متغير غير مستخدم:** $var_name في $(basename "$file")"
                    fi
                fi
            done
        fi
    done | head -10
    
    echo ""
    echo "### 🔍 دوال غير مستدعاة:"
    
    # البحث عن الدوال غير المستخدمة
    find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" | while read file; do
        if [ -f "$file" ]; then
            grep -n "function.*(" "$file" 2>/dev/null | while read line; do
                func_name=$(echo "$line" | sed 's/.*function *\([a-zA-Z_][a-zA-Z0-9_]*\).*/\1/')
                if [ -n "$func_name" ] && [ "$func_name" != "function" ]; then
                    # البحث عن استدعاء الدالة
                    usage_count=$(find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" | xargs grep -c "$func_name(" 2>/dev/null | awk -F: '{sum+=$2} END {print sum+0}')
                    if [ "$usage_count" -eq 1 ]; then
                        echo "- **دالة غير مستدعاة:** $func_name في $(basename "$file")"
                    fi
                fi
            done
        fi
    done | head -5
    
    echo ""
    echo "### 📄 ملفات CSS غير مستخدمة:"
    find "$PROJECT_ROOT" -name "*.css" | while read css_file; do
        css_name=$(basename "$css_file")
        # البحث عن استيراد الملف
        html_usage=$(find "$PROJECT_ROOT" -name "*.html" | xargs grep -l "$css_name" 2>/dev/null | wc -l)
        js_usage=$(find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" | xargs grep -l "$css_name" 2>/dev/null | wc -l)
        total_usage=$((html_usage + js_usage))
        if [ "$total_usage" -eq 0 ]; then
            echo "- **CSS غير مستخدم:** $css_name"
        fi
    done
    
    echo ""
    
} >> "$REPORT_FILE"

# ===========================================
# 5. تحليل الأداء والأمان
# ===========================================
echo -e "${GREEN}🔒 5. تحليل الأداء والأمان...${NC}"

{
    echo "## 🔒 تحليل الأمان والأداء"
    echo ""
    
    echo "### ⚠️ مشاكل أمان محتملة:"
    
    # البحث عن مشاكل أمان شائعة
    find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" -o -name "*.php" | while read file; do
        if [ -f "$file" ]; then
            # innerHTML خطير
            if grep -q "innerHTML.*=" "$file" 2>/dev/null; then
                echo "- **⚠️ استخدام innerHTML خطير:** $(basename "$file")"
            fi
            
            # eval خطير
            if grep -q "eval(" "$file" 2>/dev/null; then
                echo "- **🚨 استخدام eval خطير:** $(basename "$file")"
            fi
            
            # كلمات مرور في الكود
            if grep -qi "password.*=.*['\"]" "$file" 2>/dev/null; then
                echo "- **🔐 كلمة مرور مكشوفة محتملة:** $(basename "$file")"
            fi
        fi
    done
    
    echo ""
    echo "### 🚀 مشاكل أداء محتملة:"
    
    # البحث عن مشاكل أداء
    find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" | while read file; do
        if [ -f "$file" ]; then
            # حلقات متداخلة
            nested_loops=$(grep -c "for.*for\|while.*while" "$file" 2>/dev/null || echo 0)
            if [ "$nested_loops" -gt 0 ]; then
                echo "- **🐌 حلقات متداخلة:** $(basename "$file") - $nested_loops"
            fi
            
            # استعلامات DOM كثيرة
            dom_queries=$(grep -c "document\.\|getElementById\|querySelector" "$file" 2>/dev/null || echo 0)
            if [ "$dom_queries" -gt 10 ]; then
                echo "- **🎯 استعلامات DOM كثيرة:** $(basename "$file") - $dom_queries"
            fi
        fi
    done
    
    echo ""
    
} >> "$REPORT_FILE"

# ===========================================
# 6. اقتراحات التحسين
# ===========================================
echo -e "${WHITE}💡 6. إنشاء اقتراحات التحسين...${NC}"

{
    echo "## 💡 اقتراحات التحسين"
    echo ""
    
    echo "### 🔧 تحسينات فورية:"
    echo "1. **🗂️ تنظيم الملفات:**"
    echo "   - إنشاء مجلد components للمكونات المكررة"
    echo "   - إنشاء مجلد utils للدوال المساعدة"
    echo "   - نقل CSS المكرر إلى ملف مشترك"
    echo ""
    
    echo "2. **♻️ إعادة هيكلة الكود:**"
    echo "   - استخراج الدوال المكررة إلى مكتبة مشتركة"
    echo "   - تحويل HTML المكرر إلى مكونات قابلة للإعادة"
    echo "   - دمج ملفات CSS المتشابهة"
    echo ""
    
    echo "3. **🚀 تحسين الأداء:**"
    echo "   - استخدام lazy loading للصور"
    echo "   - ضغط ملفات CSS و JavaScript"
    echo "   - تقليل استعلامات DOM"
    echo ""
    
    echo "4. **🔒 تحسين الأمان:**"
    echo "   - استبدال innerHTML بـ textContent عند الإمكان"
    echo "   - إزالة eval() واستخدام بدائل آمنة"
    echo "   - نقل المعلومات الحساسة إلى متغيرات البيئة"
    echo ""
    
    echo "### 📋 خطة التنفيذ المقترحة:"
    echo "1. **الأسبوع الأول:** حل المشاكل الأمنية العاجلة"
    echo "2. **الأسبوع الثاني:** إزالة الكود الميت والمتغيرات غير المستخدمة"
    echo "3. **الأسبوع الثالث:** إعادة هيكلة الكود المكرر"
    echo "4. **الأسبوع الرابع:** تحسينات الأداء والاختبار"
    echo ""
    
    echo "### 🎯 مؤشرات النجاح:"
    project_size_kb=$(du -sk "$PROJECT_ROOT" | cut -f1)
    echo "- **تقليل حجم المشروع بـ 15-30%** (حالياً: ${project_size_kb}KB)"
    echo "- **تقليل وقت التحميل بـ 20-40%**"
    echo "- **تحسين قابلية الصيانة والقراءة**"
    echo "- **زيادة أمان الكود**"
    echo ""
    
} >> "$REPORT_FILE"

# ===========================================
# 7. ملخص تنفيذي
# ===========================================
{
    echo "## 📊 الملخص التنفيذي"
    echo ""
    
    # حساب النتيجة الإجمالية
    total_files=$(find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" -o -name "*.html" -o -name "*.css" | wc -l)
    large_files=$(find "$PROJECT_ROOT" -name "*.js" -o -name "*.ts" -exec wc -l {} \; | awk '$1 > 200 {count++} END {print count+0}')
    
    if [ "$total_files" -gt 0 ]; then
        complexity_ratio=$((large_files * 100 / total_files))
    else
        complexity_ratio=0
    fi
    
    echo "### 🎯 النتيجة الإجمالية:"
    if [ "$complexity_ratio" -lt 20 ]; then
        echo "**✅ ممتاز (${complexity_ratio}% ملفات معقدة)**"
        grade="A"
    elif [ "$complexity_ratio" -lt 40 ]; then
        echo "**👍 جيد (${complexity_ratio}% ملفات معقدة)**"
        grade="B"
    elif [ "$complexity_ratio" -lt 60 ]; then
        echo "**⚠️ يحتاج تحسين (${complexity_ratio}% ملفات معقدة)**"
        grade="C"
    else
        echo "**🚨 يحتاج إعادة هيكلة (${complexity_ratio}% ملفات معقدة)**"
        grade="D"
    fi
    
    echo ""
    echo "### 📈 الإحصائيات الرئيسية:"
    echo "| المؤشر | القيمة |"
    echo "|---------|---------|"
    echo "| إجمالي ملفات الكود | $total_files |"
    echo "| الملفات المعقدة | $large_files |"
    echo "| التقييم العام | $grade |"
    echo "| حجم المشروع | $(du -sh "$PROJECT_ROOT" | cut -f1) |"
    echo ""
    
    echo "### 🎯 الأولويات:"
    echo "1. **🚨 عاجل:** المشاكل الأمنية"
    echo "2. **⚡ مهم:** إزالة الكود الميت"
    echo "3. **🔄 متوسط:** معالجة التكرار"
    echo "4. **🚀 مرغوب:** تحسينات الأداء"
    echo ""
    
} >> "$REPORT_FILE"

# ===========================================
# 8. إنهاء التقرير وعرض النتائج
# ===========================================
echo -e "${GREEN}✅ تم إنهاء المراجعة الشاملة!${NC}"
echo ""

# عرض ملخص سريع
echo -e "${WHITE}📊 ملخص سريع:${NC}"
echo "🔍 تم فحص $total_files ملف"
echo "⚠️ تم العثور على $large_files ملف معقد"
echo "📄 التقرير الكامل: $REPORT_FILE"

# فتح التقرير إذا كان متاحاً
if command -v code &> /dev/null; then
    echo ""
    echo -e "${CYAN}📖 فتح التقرير في VS Code...${NC}"
    code "$REPORT_FILE"
elif command -v cat &> /dev/null; then
    echo ""
    echo -e "${CYAN}📖 عرض التقرير:${NC}"
    echo "========================"
    cat "$REPORT_FILE"
fi

# إنشاء ملف TODO للمتابعة
TODO_FILE="$REPORT_DIR/TODO_improvements.md"
cat > "$TODO_FILE" << EOF
# 📋 قائمة المهام للتحسين

## 🚨 عاجل - مشاكل أمان
- [ ] فحص استخدامات innerHTML
- [ ] إزالة eval() إن وجد
- [ ] نقل كلمات المرور للمتغيرات البيئية

## ⚡ مهم - تنظيف الكود
- [ ] إزالة المتغيرات غير المستخدمة
- [ ] إزالة الدوال غير المستدعاة
- [ ] إزالة ملفات CSS غير المستخدمة

## 🔄 متوسط - معالجة التكرار
- [ ] استخراج الدوال المكررة
- [ ] توحيد HTML المكرر
- [ ] دمج CSS المتشابه

## 🚀 مرغوب - تحسين الأداء
- [ ] تحسين الحلقات المتداخلة
- [ ] تقليل استعلامات DOM
- [ ] إضافة lazy loading

---
**تم إنشاؤه:** $(date)
**من:** System Review Hook
EOF

echo "📋 تم إنشاء قائمة المهام: $TODO_FILE"
echo ""
echo -e "${GREEN}🎉 المراجعة اكتملت بنجاح!${NC}"