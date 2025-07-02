# 📘 MANUAL DE COMPILACIÓN - INVENTARIO AGENT v1.1

## 📋 REQUISITOS PREVIOS

- Windows 10/11 
- .NET 6.0 SDK o superior
- Acceso a internet para descargar paquetes

**Verificar .NET instalado:**
```cmd
dotnet --version
```

---

## 🔧 CONFIGURACIÓN DE MYSQL

Ejecutar en el servidor MySQL (172.17.0.12):

```sql
-- Crear usuario universal
DROP USER IF EXISTS 'inv_agent'@'%';
CREATE USER 'inv_agent'@'%' IDENTIFIED BY 'svr_inde_1';
GRANT ALL PRIVILEGES ON inventario_equipos.* TO 'inv_agent'@'%';
FLUSH PRIVILEGES;

-- Verificar creación
SELECT host, user FROM mysql.user WHERE user='inv_agent';
```

---

## 📁 PASO 1: PREPARACIÓN DE ARCHIVOS

Los archivos del proyecto ya están listos en la carpeta **"para compilar"**:
- `InventarioAgent.csproj`
- `InventarioAgent.cs`

**Copiar estos archivos a la ubicación de compilación:**

```cmd
cd C:\
mkdir InventarioAgent
cd C:\InventarioAgent
```

Copiar los archivos desde la carpeta **"para compilar"** a `C:\InventarioAgent\`

---

## 🔨 PASO 2: COMPILACIÓN

Abrir **Command Prompt como Administrador** y ejecutar:

```cmd
cd C:\InventarioAgent

REM Limpiar proyecto anterior (si existe)
dotnet clean

REM Restaurar paquetes de NuGet
dotnet restore

REM Compilar ejecutable único autocontenido
dotnet publish -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true
```

**⏱️ Tiempo estimado:** 2-5 minutos (dependiendo de la velocidad de internet)

---

## 📦 PASO 3: UBICACIÓN DEL EJECUTABLE FINAL

El ejecutable compilado estará en:
```
C:\InventarioAgent\bin\Release\net6.0\win-x64\publish\InventarioAgent.exe
```

**📊 Características del archivo:**
- **Tamaño:** 25-30 MB
- **Tipo:** Ejecutable único autocontenido
- **Requisitos:** Ninguno (no necesita .NET instalado en el equipo destino)

---

## ✅ PASO 4: VERIFICACIÓN

### Probar en el equipo de desarrollo:
```cmd
cd C:\InventarioAgent\bin\Release\net6.0\win-x64\publish\
InventarioAgent.exe
```

### **Resultado esperado:**
```
=== INVENTARIO AGENT v1.1 ===
Agente de inventario automático para equipos Windows
Conectando a: 172.17.0.12:3306

→ Iniciando recolección de información del equipo...
   ✓ Equipo: NOMBRE-PC
   ✓ Usuario: usuario
   → Obteniendo información de red...
   ✓ IP Local: 192.168.1.100
   ✓ IP Pública: 201.123.45.67
   [... más información del hardware ...]

==================================================
GUARDANDO EN BASE DE DATOS
==================================================
→ Probando conectividad a la base de datos...
→ Estableciendo conexión...
✓ Conexión establecida exitosamente
→ Equipo nuevo, insertando registro...
✓ Nuevo equipo registrado con ID: 5

✓ ¡Inventario completado exitosamente!
✓ Equipo 'NOMBRE-PC' registrado/actualizado
```

### Verificar en MySQL:
```sql
SELECT nombre_equipo, ip_local, sistema_operativo, updated_at 
FROM inventario_equipos.equipos_info 
ORDER BY updated_at DESC LIMIT 5;
```

---

## 🚀 PASO 5: DISTRIBUCIÓN

### **Para un solo equipo:**
1. Copiar `InventarioAgent.exe` al equipo destino
2. Ejecutar desde cualquier ubicación
3. No requiere instalación ni dependencias

### **Para múltiples equipos:**

#### **Opción A: Carpeta compartida de red**
```cmd
copy InventarioAgent.exe \\servidor\inventario\
```

Ejecutar desde equipos remotos:
```cmd
\\servidor\inventario\InventarioAgent.exe
```

#### **Opción B: Script de distribución automática**
Crear archivo `ejecutar_inventario.bat`:

```batch
@echo off
title Inventario de Equipos - INDE

echo ================================================
echo    INVENTARIO AUTOMATICO DE EQUIPOS
echo    Equipo: %COMPUTERNAME%
echo ================================================
echo.

REM Ejecutar inventario
InventarioAgent.exe

echo.
echo Inventario completado para: %COMPUTERNAME%
timeout /t 5
```

#### **Opción C: Distribución por GPO (Dominio)**
1. Copiar `InventarioAgent.exe` a `\\dominio\SYSVOL\scripts\`
2. Crear GPO de script de inicio
3. Programar ejecución automática

---

## 🔍 SOLUCIÓN DE PROBLEMAS

### **Error de compilación:**
```cmd
dotnet clean
rmdir /s bin obj
dotnet restore
dotnet build
```

### **Error "dotnet no se reconoce":**
- Descargar e instalar .NET 6.0 SDK desde microsoft.com
- Reiniciar Command Prompt

### **Error de conexión MySQL:**
- Verificar conectividad: `ping 172.17.0.12`
- Verificar puerto: `telnet 172.17.0.12 3306`
- Verificar usuario en MySQL: `SELECT user, host FROM mysql.user WHERE user='inv_agent';`

### **Error de permisos en Windows:**
- Ejecutar Command Prompt como Administrador
- Verificar permisos de escritura en C:\

### **El ejecutable no funciona en otro equipo:**
- Verificar que el equipo destino tenga acceso a 172.17.0.12:3306
- Revisar configuración de firewall
- Ejecutar como Administrador si es necesario

---

## 📊 CARACTERÍSTICAS DEL AGENTE COMPILADO

✅ **Archivo único** - No necesita archivos adicionales  
✅ **Autocontenido** - No requiere .NET instalado en destino  
✅ **Universal** - Funciona en cualquier equipo Windows x64  
✅ **Conectividad** - Se conecta desde cualquier IP de la red  
✅ **Actualización inteligente** - Detecta equipos existentes  
✅ **Información completa** - Hardware, software, red, fechas  
✅ **Compatible con automatización** - Scripts, GPO, Task Scheduler  

---

## 📈 ESCALABILIDAD

### **Para inventario masivo:**
- **1-10 equipos:** Ejecución manual
- **10-50 equipos:** Script de red compartida
- **50+ equipos:** GPO o Task Scheduler programado
- **Monitoreo:** Consultas SQL para reportes automáticos

### **Consulta de ejemplo para reporte:**
```sql
SELECT 
    nombre_equipo,
    ip_local,
    sistema_operativo,
    fabricante,
    modelo,
    DATE(updated_at) as ultima_actualizacion
FROM inventario_equipos.equipos_info 
WHERE DATE(updated_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
ORDER BY updated_at DESC;
```

---

## 🎯 RESUMEN

1. **Configurar** usuario MySQL (`inv_agent@%`)
2. **Copiar** archivos del proyecto a `C:\InventarioAgent\`
3. **Compilar** con `dotnet publish`
4. **Probar** el ejecutable generado
5. **Distribuir** a todos los equipos de la red
