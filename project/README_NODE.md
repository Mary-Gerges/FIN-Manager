# FinanceIQ Node.js Backend

تم إضافة Backend كامل بـ Node.js للآتي:

- Register / Login
- حفظ بيانات المستخدم
- Transactions
- Budgets
- Settings / Profile name
- Notifications
- Clear all data

## طريقة التشغيل

1. افتحي Terminal داخل فولدر `node-backend`:

```bash
npm install
npm start
```

2. شغلي الواجهة بفتح `test.html` أو عن طريق Live Server.

3. السيرفر يعمل على:

```text
http://localhost:3000
```

البيانات بتتخزن في:

```text
node-backend/data/users.json
```

لو السيرفر مش شغال، الكود هيستخدم localStorage مؤقتًا بدون ما يطلع Error.
