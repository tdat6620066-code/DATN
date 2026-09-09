<label class="form-label">Ngân hàng</label>
<input name="bank_name" class="form-control mb-2" maxlength="100" placeholder="Ví dụ: MB Bank" autocomplete="off" required>
<label class="form-label">Số tài khoản</label>
<input name="bank_account_number" class="form-control mb-2" inputmode="numeric" pattern="[0-9]{6,34}" maxlength="34" autocomplete="off" required>
<label class="form-label">Tên chủ tài khoản</label>
<input name="bank_account_holder" class="form-control mb-3" maxlength="150" autocomplete="off" required>
<label class="d-block mb-3"><input type="checkbox" name="recipient_confirmed" value="1" required> Tôi xác nhận thông tin trên là chính xác.</label>
