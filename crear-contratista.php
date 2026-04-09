<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Crear Contratista';
$currentPage = 'contratistas.php';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validaciones
    $name = trim($_POST['name'] ?? '');
    $contractorType = $_POST['contractor_type'] ?? '';
    $companyName = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $secondaryPhone = trim($_POST['secondary_phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $specialties = trim($_POST['specialties'] ?? '');
    $taxId = trim($_POST['tax_id'] ?? '');
    $bankAccount = trim($_POST['bank_account'] ?? '');
    $emergencyContact = trim($_POST['emergency_contact'] ?? '');
    $emergencyPhone = trim($_POST['emergency_phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $hourlyRate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null;
    $rating = !empty($_POST['rating']) ? (float)$_POST['rating'] : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Validar campos requeridos
    if (empty($name)) {
        $errors[] = "El nombre del contratista es obligatorio";
    }
    if (empty($contractorType)) {
        $errors[] = "El tipo de contratista es obligatorio";
    }
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El email no es válido";
    }
    if (!empty($rating) && ($rating < 0 || $rating > 5)) {
        $errors[] = "La calificación debe estar entre 0 y 5";
    }
    
    // Verificar si ya existe
    if (!empty($email)) {
        $existing = db()->selectValue(
            "SELECT id FROM contractors WHERE email = ?",
            [$email]
        );
        if ($existing) {
            $errors[] = "Ya existe un contratista con ese email";
        }
    }
    
    if (empty($errors)) {
        try {
            $contractorId = db()->insert('contractors', [
                'name' => $name,
                'contractor_type' => $contractorType,
                'company_name' => $companyName,
                'phone' => $phone,
                'secondary_phone' => $secondaryPhone,
                'email' => $email,
                'address' => $address,
                'city' => $city,
                'specialties' => $specialties,
                'tax_id' => $taxId,
                'bank_account' => $bankAccount,
                'emergency_contact' => $emergencyContact,
                'emergency_phone' => $emergencyPhone,
                'hourly_rate' => $hourlyRate,
                'rating' => $rating,
                'notes' => $notes,
                'is_active' => $isActive,
                'created_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Registrar en log
            db()->insert('activity_log', [
                'user_id' => $_SESSION['user_id'],
                'action' => 'create',
                'entity_type' => 'contractor',
                'entity_id' => $contractorId,
                'description' => "Contratista '{$name}' creado",
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $_SESSION['success_message'] = "Contratista creado exitosamente";
            header("Location: contratistas.php");
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Error al crear el contratista: " . $e->getMessage();
        }
    }
}

include 'header.php';
include 'sidebar.php';
?>

<style>
    .crear-contratista-container {
        padding: 30px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    .form-card-contratista {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 30px;
    }
    
    .form-header-contratista {
        padding: 24px 32px;
        border-bottom: 1px solid #e5e7eb;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .form-header-contratista h2 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .form-body-contratista {
        padding: 32px;
    }
    
    .form-section-contratista {
        margin-bottom: 36px;
    }
    
    .form-section-title-contratista {
        font-size: 18px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-section-title-contratista i {
        color: #667eea;
    }
    
    .form-row-contratista {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .form-group-contratista {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .form-group-contratista.full-width {
        grid-column: 1 / -1;
    }
    
    .form-label-contratista {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
    }
    
    .form-label-contratista.required::after {
        content: '*';
        color: #ef4444;
        margin-left: 4px;
    }
    
    .form-input-contratista,
    .form-select-contratista,
    .form-textarea-contratista {
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
        font-family: 'Inter', sans-serif;
        width: 100%;
    }
    
    .form-input-contratista:focus,
    .form-select-contratista:focus,
    .form-textarea-contratista:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-textarea-contratista {
        resize: vertical;
        min-height: 100px;
    }
    
    .form-help-contratista {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }
    
    .form-checkbox-group {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px;
        background: #f9fafb;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }
    
    .form-checkbox-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: #667eea;
    }
    
    .form-checkbox-group label {
        cursor: pointer;
        font-weight: 500;
        color: #374151;
        margin: 0;
    }
    
    .alert-contratista {
        padding: 16px 20px;
        border-radius: 8px;
        margin-bottom: 24px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    
    .alert-danger-contratista {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }
    
    .alert-danger-contratista i {
        color: #dc2626;
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .form-actions-contratista {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        padding-top: 24px;
        border-top: 1px solid #e5e7eb;
    }
    
    .btn-primary-contratista {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 14px 32px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .btn-primary-contratista:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        color: white;
    }
    
    .btn-secondary-contratista {
        background: #f3f4f6;
        color: #4b5563;
        padding: 14px 32px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-secondary-contratista:hover {
        background: #e5e7eb;
        color: #4b5563;
    }
    
    @media (max-width: 768px) {
        .crear-contratista-container {
            padding: 16px;
        }
        
        .form-body-contratista {
            padding: 20px;
        }
        
        .form-row-contratista {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .form-actions-contratista {
            flex-direction: column-reverse;
        }
        
        .form-actions-contratista button,
        .form-actions-contratista a {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="crear-contratista-container">
    
    <div class="form-card-contratista">
        <div class="form-header-contratista">
            <h2>
                <i class="fas fa-user-plus"></i>
                Crear Nuevo Contratista
            </h2>
        </div>
        
        <div class="form-body-contratista">
            
            <?php if (!empty($errors)): ?>
                <div class="alert-contratista alert-danger-contratista">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Se encontraron los siguientes errores:</strong>
                        <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="contractorForm">
                
                <!-- Información General -->
                <div class="form-section-contratista">
                    <h3 class="form-section-title-contratista">
                        <i class="fas fa-info-circle"></i>
                        Información General
                    </h3>
                    
                    <div class="form-row-contratista">
                        <div class="form-group-contratista">
                            <label class="form-label-contratista required">Nombre del Contratista</label>
                            <input type="text" 
                                   name="name" 
                                   class="form-input-contratista" 
                                   placeholder="Nombre completo o razón social"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group-contratista">
                            <label class="form-label-contratista required">Tipo de Contratista</label>
                            <select name="contractor_type" class="form-select-contratista" required>
                                <option value="">Seleccione un tipo</option>
                                <?php
                                $types = ['Arquitecto', 'Ingeniero', 'Albañil', 'Electricista', 'Plomero', 'Pintor', 'Carpintero', 'Proveedor', 'Otro'];
                                foreach ($types as $type):
                                ?>
                                    <option value="<?php echo $type; ?>" <?php echo ($_POST['contractor_type'] ?? '') === $type ? 'selected' : ''; ?>>
                                        <?php echo $type; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Nombre de la Empresa</label>
                            <input type="text" 
                                   name="company_name" 
                                   class="form-input-contratista" 
                                   placeholder="Nombre comercial o empresa"
                                   value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row-contratista">
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Teléfono Principal</label>
                            <input type="tel" 
                                   name="phone" 
                                   class="form-input-contratista" 
                                   placeholder="(809) 000-0000"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Teléfono Secundario</label>
                            <input type="tel" 
                                   name="secondary_phone" 
                                   class="form-input-contratista" 
                                   placeholder="(809) 000-0000"
                                   value="<?php echo htmlspecialchars($_POST['secondary_phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Email</label>
                            <input type="email" 
                                   name="email" 
                                   class="form-input-contratista" 
                                   placeholder="correo@ejemplo.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Ubicación -->
                <div class="form-section-contratista">
                    <h3 class="form-section-title-contratista">
                        <i class="fas fa-map-marker-alt"></i>
                        Ubicación
                    </h3>
                    
                    <div class="form-row-contratista">
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Dirección</label>
                            <input type="text" 
                                   name="address" 
                                   class="form-input-contratista" 
                                   placeholder="Calle, número, sector"
                                   value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Ciudad</label>
                            <input type="text" 
                                   name="city" 
                                   class="form-input-contratista" 
                                   placeholder="Santo Domingo"
                                   value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Información Profesional -->
                <div class="form-section-contratista">
                    <h3 class="form-section-title-contratista">
                        <i class="fas fa-briefcase"></i>
                        Información Profesional
                    </h3>
                    
                    <div class="form-row-contratista">
                        <div class="form-group-contratista full-width">
                            <label class="form-label-contratista">Especialidades</label>
                            <textarea name="specialties" 
                                      class="form-textarea-contratista" 
                                      placeholder="Lista de especialidades, certificaciones o áreas de experiencia..."><?php echo htmlspecialchars($_POST['specialties'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row-contratista">
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Tarifa por Hora ($)</label>
                            <input type="number" 
                                   name="hourly_rate" 
                                   class="form-input-contratista" 
                                   min="0"
                                   step="0.01"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['hourly_rate'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Calificación (0-5)</label>
                            <input type="number" 
                                   name="rating" 
                                   class="form-input-contratista" 
                                   min="0"
                                   max="5"
                                   step="0.1"
                                   placeholder="0.0"
                                   value="<?php echo htmlspecialchars($_POST['rating'] ?? ''); ?>">
                            <small class="form-help-contratista">Calificación basada en trabajos anteriores</small>
                        </div>
                    </div>
                </div>
                
                <!-- Información Fiscal y Bancaria -->
                <div class="form-section-contratista">
                    <h3 class="form-section-title-contratista">
                        <i class="fas fa-file-invoice-dollar"></i>
                        Información Fiscal y Bancaria
                    </h3>
                    
                    <div class="form-row-contratista">
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">RNC / Cédula</label>
                            <input type="text" 
                                   name="tax_id" 
                                   class="form-input-contratista" 
                                   placeholder="000-0000000-0"
                                   value="<?php echo htmlspecialchars($_POST['tax_id'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Cuenta Bancaria</label>
                            <input type="text" 
                                   name="bank_account" 
                                   class="form-input-contratista" 
                                   placeholder="Banco y número de cuenta"
                                   value="<?php echo htmlspecialchars($_POST['bank_account'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Contacto de Emergencia -->
                <div class="form-section-contratista">
                    <h3 class="form-section-title-contratista">
                        <i class="fas fa-phone-alt"></i>
                        Contacto de Emergencia
                    </h3>
                    
                    <div class="form-row-contratista">
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Nombre del Contacto</label>
                            <input type="text" 
                                   name="emergency_contact" 
                                   class="form-input-contratista" 
                                   placeholder="Nombre completo"
                                   value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group-contratista">
                            <label class="form-label-contratista">Teléfono de Emergencia</label>
                            <input type="tel" 
                                   name="emergency_phone" 
                                   class="form-input-contratista" 
                                   placeholder="(809) 000-0000"
                                   value="<?php echo htmlspecialchars($_POST['emergency_phone'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Notas Adicionales -->
                <div class="form-section-contratista">
                    <h3 class="form-section-title-contratista">
                        <i class="fas fa-sticky-note"></i>
                        Notas y Observaciones
                    </h3>
                    
                    <div class="form-group-contratista full-width">
                        <label class="form-label-contratista">Notas</label>
                        <textarea name="notes" 
                                  class="form-textarea-contratista" 
                                  placeholder="Cualquier información adicional relevante..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-checkbox-group">
                        <input type="checkbox" 
                               name="is_active" 
                               id="isActive"
                               value="1"
                               <?php echo (!isset($_POST['is_active']) || $_POST['is_active']) ? 'checked' : ''; ?>>
                        <label for="isActive">Contratista activo (disponible para asignar a proyectos)</label>
                    </div>
                </div>
                
                <!-- Botones de Acción -->
                <div class="form-actions-contratista">
                    <a href="contratistas.php" class="btn-secondary-contratista">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </a>
                    <button type="submit" class="btn-primary-contratista">
                        <i class="fas fa-save"></i>
                        Crear Contratista
                    </button>
                </div>
                
            </form>
        </div>
    </div>
    
</div>

<?php include 'footer.php'; ?>