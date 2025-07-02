using System;
using System.Management;
using System.Net;
using System.Net.NetworkInformation;
using MySqlConnector;
using System.Text;
using System.Linq;
using System.IO;

namespace InventarioAgent
{
    class Program
    {
        private static string connectionString = "Server=172.17.0.12;Port=3306;Database=inventario_equipos;Uid=inv_agent;Pwd=svr_inde_1;Connection Timeout=30;Command Timeout=60;SslMode=None;AllowPublicKeyRetrieval=true;";
        
        static void Main(string[] args)
        {
            Console.WriteLine("=== INVENTARIO AGENT v1.1 ===");
            Console.WriteLine("Agente de inventario automático para equipos Windows");
            Console.WriteLine("Conectando a: 172.17.0.12:3306\n");
            
            try
            {
                // Recolectar información del sistema
                Console.WriteLine("→ Iniciando recolección de información del equipo...");
                var equipoInfo = RecolectarInformacion();
                
                // Mostrar información recolectada
                MostrarInformacion(equipoInfo);
                
                // Guardar en base de datos
                Console.WriteLine("\n" + new string('=', 50));
                Console.WriteLine("GUARDANDO EN BASE DE DATOS");
                Console.WriteLine(new string('=', 50));
                GuardarEnBaseDatos(equipoInfo);
                
                Console.WriteLine("\n✓ ¡Inventario completado exitosamente!");
                Console.WriteLine($"✓ Equipo '{equipoInfo.NombreEquipo}' registrado/actualizado");
                Console.WriteLine($"✓ Fecha de captura: {equipoInfo.FechaCaptura}");
                
            }
            catch (Exception ex)
            {
                Console.WriteLine($"\n✗ ERROR: {ex.Message}");
                Console.WriteLine("\nDetalles del error para soporte técnico:");
                Console.WriteLine($"Tipo: {ex.GetType().Name}");
                if (ex.InnerException != null)
                {
                    Console.WriteLine($"Error interno: {ex.InnerException.Message}");
                }
            }
            finally
            {
                Console.WriteLine("\nPresiona cualquier tecla para salir...");
                Console.ReadKey();
            }
        }
        
        static EquipoInfo RecolectarInformacion()
        {
            var equipo = new EquipoInfo();
            
            try
            {
                // Información básica del sistema
                equipo.NombreEquipo = Environment.MachineName;
                equipo.UsuarioSistema = Environment.UserName;
                equipo.FechaCaptura = DateTime.Now;
                
                Console.WriteLine($"   ✓ Equipo: {equipo.NombreEquipo}");
                Console.WriteLine($"   ✓ Usuario: {equipo.UsuarioSistema}");
                
                // Información de red
                Console.WriteLine("   → Obteniendo información de red...");
                equipo.IpLocal = ObtenerIPLocal();
                equipo.IpEquipo = ObtenerIPPublica();
                Console.WriteLine($"   ✓ IP Local: {equipo.IpLocal}");
                Console.WriteLine($"   ✓ IP Pública: {equipo.IpEquipo}");
                
                // Información del sistema operativo
                Console.WriteLine("   → Analizando sistema operativo...");
                using (var searcher = new ManagementObjectSearcher("SELECT * FROM Win32_OperatingSystem"))
                {
                    foreach (ManagementObject os in searcher.Get())
                    {
                        equipo.SistemaOperativo = $"{os["Caption"]} {os["Version"]} {os["OSArchitecture"]}";
                        Console.WriteLine($"   ✓ SO: {equipo.SistemaOperativo}");
                        break;
                    }
                }
                
                // Información del procesador
                Console.WriteLine("   → Detectando procesador...");
                using (var searcher = new ManagementObjectSearcher("SELECT * FROM Win32_Processor"))
                {
                    foreach (ManagementObject processor in searcher.Get())
                    {
                        equipo.Procesador = $"{processor["Name"]} - {processor["NumberOfCores"]} núcleos";
                        Console.WriteLine($"   ✓ CPU: {equipo.Procesador}");
                        break;
                    }
                }
                
                // Información de memoria RAM
                Console.WriteLine("   → Calculando memoria RAM...");
                using (var searcher = new ManagementObjectSearcher("SELECT * FROM Win32_PhysicalMemory"))
                {
                    long totalMemory = 0;
                    foreach (ManagementObject memory in searcher.Get())
                    {
                        totalMemory += Convert.ToInt64(memory["Capacity"]);
                    }
                    equipo.MemoriaRam = $"Total: {totalMemory / (1024 * 1024 * 1024)} GB";
                    Console.WriteLine($"   ✓ RAM: {equipo.MemoriaRam}");
                }
                
                // Información de discos duros
                Console.WriteLine("   → Analizando discos duros...");
                var discos = new StringBuilder();
                using (var searcher = new ManagementObjectSearcher("SELECT * FROM Win32_LogicalDisk WHERE DriveType=3"))
                {
                    foreach (ManagementObject disk in searcher.Get())
                    {
                        var size = Convert.ToInt64(disk["Size"]) / (1024 * 1024 * 1024);
                        var free = Convert.ToInt64(disk["FreeSpace"]) / (1024 * 1024 * 1024);
                        var usado = size - free;
                        var porcentaje = size > 0 ? (usado * 100) / size : 0;
                        discos.Append($"Unidad {disk["DeviceID"]} {usado}GB de {size}GB ({porcentaje}% usado); ");
                    }
                }
                equipo.DiscosDuros = discos.ToString().TrimEnd(';', ' ');
                Console.WriteLine($"   ✓ Discos: {equipo.DiscosDuros}");
                
                // Información de tarjetas de red
                Console.WriteLine("   → Detectando tarjetas de red...");
                var redes = new StringBuilder();
                using (var searcher = new ManagementObjectSearcher("SELECT * FROM Win32_NetworkAdapter WHERE NetConnectionStatus=2"))
                {
                    foreach (ManagementObject adapter in searcher.Get())
                    {
                        redes.Append($"{adapter["Name"]}; ");
                    }
                }
                equipo.TarjetasRed = redes.ToString().TrimEnd(';', ' ');
                Console.WriteLine($"   ✓ Red: {equipo.TarjetasRed}");
                
                // Información del fabricante y modelo
                Console.WriteLine("   → Obteniendo información del hardware...");
                using (var searcher = new ManagementObjectSearcher("SELECT * FROM Win32_ComputerSystem"))
                {
                    foreach (ManagementObject system in searcher.Get())
                    {
                        equipo.Fabricante = system["Manufacturer"]?.ToString() ?? "No disponible";
                        equipo.Modelo = system["Model"]?.ToString() ?? "No disponible";
                        Console.WriteLine($"   ✓ Fabricante: {equipo.Fabricante}");
                        Console.WriteLine($"   ✓ Modelo: {equipo.Modelo}");
                        break;
                    }
                }
                
                // Número de serie
                using (var searcher = new ManagementObjectSearcher("SELECT * FROM Win32_BIOS"))
                {
                    foreach (ManagementObject bios in searcher.Get())
                    {
                        equipo.NumeroSerie = bios["SerialNumber"]?.ToString() ?? "No disponible";
                        Console.WriteLine($"   ✓ Serie: {equipo.NumeroSerie}");
                        break;
                    }
                }
                
                equipo.UserAgent = "InventarioAgent/1.1";
                equipo.Navegador = "InventarioAgent/1.1";
                equipo.OrigenDatos = "InventarioAgent";
                
                Console.WriteLine("\n✓ Recolección de información completada");
            }
            catch (Exception ex)
            {
                throw new Exception($"Error al recolectar información del sistema: {ex.Message}");
            }
            
            return equipo;
        }
        
        static string ObtenerIPLocal()
        {
            try
            {
                var host = Dns.GetHostEntry(Dns.GetHostName());
                var ip = host.AddressList.FirstOrDefault(x => x.AddressFamily == System.Net.Sockets.AddressFamily.InterNetwork);
                return ip?.ToString() ?? "0.0.0.0";
            }
            catch
            {
                return "0.0.0.0";
            }
        }
        
        static string ObtenerIPPublica()
        {
            try
            {
                using (var client = new WebClient())
                {
                    client.Headers.Add("User-Agent", "InventarioAgent/1.1");
                    return client.DownloadString("https://api.ipify.org").Trim();
                }
            }
            catch
            {
                return "0.0.0.0";
            }
        }
        
        static void MostrarInformacion(EquipoInfo equipo)
        {
            Console.WriteLine("\n" + new string('=', 50));
            Console.WriteLine("RESUMEN DE INFORMACIÓN RECOLECTADA");
            Console.WriteLine(new string('=', 50));
            Console.WriteLine($"Nombre del equipo    : {equipo.NombreEquipo}");
            Console.WriteLine($"Usuario actual       : {equipo.UsuarioSistema}");
            Console.WriteLine($"IP Local            : {equipo.IpLocal}");
            Console.WriteLine($"IP Pública          : {equipo.IpEquipo}");
            Console.WriteLine($"Sistema Operativo   : {equipo.SistemaOperativo}");
            Console.WriteLine($"Procesador          : {equipo.Procesador}");
            Console.WriteLine($"Memoria RAM         : {equipo.MemoriaRam}");
            Console.WriteLine($"Discos              : {equipo.DiscosDuros}");
            Console.WriteLine($"Tarjetas de Red     : {equipo.TarjetasRed}");
            Console.WriteLine($"Fabricante          : {equipo.Fabricante}");
            Console.WriteLine($"Modelo              : {equipo.Modelo}");
            Console.WriteLine($"Número de Serie     : {equipo.NumeroSerie}");
            Console.WriteLine($"Fecha de Captura    : {equipo.FechaCaptura}");
        }
        
        static void GuardarEnBaseDatos(EquipoInfo equipo)
        {
            try
            {
                Console.WriteLine("→ Probando conectividad a la base de datos...");
                
                using (var connection = new MySqlConnection(connectionString))
                {
                    Console.WriteLine("→ Estableciendo conexión...");
                    connection.Open();
                    Console.WriteLine("✓ Conexión establecida exitosamente");
                    
                    // Verificar si el equipo ya existe (por nombre de equipo)
                    Console.WriteLine($"→ Verificando si el equipo '{equipo.NombreEquipo}' ya existe...");
                    var checkQuery = "SELECT id FROM equipos_info WHERE nombre_equipo = @nombre_equipo";
                    using (var checkCmd = new MySqlCommand(checkQuery, connection))
                    {
                        checkCmd.Parameters.AddWithValue("@nombre_equipo", equipo.NombreEquipo);
                        var existingId = checkCmd.ExecuteScalar();
                        
                        if (existingId != null)
                        {
                            // Actualizar registro existente
                            Console.WriteLine($"→ Equipo encontrado (ID: {existingId}), actualizando información...");
                            ActualizarEquipo(connection, Convert.ToInt32(existingId), equipo);
                            Console.WriteLine("✓ Equipo actualizado exitosamente");
                        }
                        else
                        {
                            // Insertar nuevo registro
                            Console.WriteLine("→ Equipo nuevo, insertando registro...");
                            var nuevoId = InsertarEquipo(connection, equipo);
                            Console.WriteLine($"✓ Nuevo equipo registrado con ID: {nuevoId}");
                        }
                    }
                }
            }
            catch (MySqlException ex)
            {
                throw new Exception($"Error de conexión MySQL: {ex.Message} (Código: {ex.Number})");
            }
            catch (Exception ex)
            {
                throw new Exception($"Error al guardar en base de datos: {ex.Message}");
            }
        }
        
        static void ActualizarEquipo(MySqlConnection connection, int id, EquipoInfo equipo)
        {
            var query = @"UPDATE equipos_info SET 
                ip_equipo = @ip_equipo,
                ip_local = @ip_local,
                procesador = @procesador,
                memoria_ram = @memoria_ram,
                disco_duro = @disco_duro,
                tarjeta_red = @tarjeta_red,
                usuario_sistema = @usuario_sistema,
                fecha_captura = @fecha_captura,
                user_agent = @user_agent,
                navegador = @navegador,
                updated_at = CURRENT_TIMESTAMP,
                sistema_operativo = @sistema_operativo,
                fabricante = @fabricante,
                modelo = @modelo,
                numero_serie = @numero_serie,
                origen_datos = @origen_datos
                WHERE id = @id";
                
            using (var cmd = new MySqlCommand(query, connection))
            {
                cmd.Parameters.AddWithValue("@id", id);
                AgregarParametros(cmd, equipo);
                cmd.ExecuteNonQuery();
            }
        }
        
        static int InsertarEquipo(MySqlConnection connection, EquipoInfo equipo)
        {
            var query = @"INSERT INTO equipos_info 
                (ip_equipo, ip_local, procesador, memoria_ram, disco_duro, tarjeta_red, 
                usuario_sistema, fecha_captura, user_agent, navegador, sistema_operativo, 
                nombre_equipo, fabricante, modelo, numero_serie, origen_datos) 
                VALUES 
                (@ip_equipo, @ip_local, @procesador, @memoria_ram, @disco_duro, @tarjeta_red, 
                @usuario_sistema, @fecha_captura, @user_agent, @navegador, @sistema_operativo, 
                @nombre_equipo, @fabricante, @modelo, @numero_serie, @origen_datos);
                SELECT LAST_INSERT_ID();";
                
            using (var cmd = new MySqlCommand(query, connection))
            {
                AgregarParametros(cmd, equipo);
                var result = cmd.ExecuteScalar();
                return Convert.ToInt32(result);
            }
        }
        
        static void AgregarParametros(MySqlCommand cmd, EquipoInfo equipo)
        {
            cmd.Parameters.AddWithValue("@ip_equipo", equipo.IpEquipo);
            cmd.Parameters.AddWithValue("@ip_local", equipo.IpLocal);
            cmd.Parameters.AddWithValue("@procesador", equipo.Procesador);
            cmd.Parameters.AddWithValue("@memoria_ram", equipo.MemoriaRam);
            cmd.Parameters.AddWithValue("@disco_duro", equipo.DiscosDuros);
            cmd.Parameters.AddWithValue("@tarjeta_red", equipo.TarjetasRed);
            cmd.Parameters.AddWithValue("@usuario_sistema", equipo.UsuarioSistema);
            cmd.Parameters.AddWithValue("@fecha_captura", equipo.FechaCaptura);
            cmd.Parameters.AddWithValue("@user_agent", equipo.UserAgent);
            cmd.Parameters.AddWithValue("@navegador", equipo.Navegador);
            cmd.Parameters.AddWithValue("@sistema_operativo", equipo.SistemaOperativo);
            cmd.Parameters.AddWithValue("@nombre_equipo", equipo.NombreEquipo);
            cmd.Parameters.AddWithValue("@fabricante", equipo.Fabricante);
            cmd.Parameters.AddWithValue("@modelo", equipo.Modelo);
            cmd.Parameters.AddWithValue("@numero_serie", equipo.NumeroSerie);
            cmd.Parameters.AddWithValue("@origen_datos", equipo.OrigenDatos);
        }
    }
    
    public class EquipoInfo
    {
        public string IpEquipo { get; set; }
        public string IpLocal { get; set; }
        public string Procesador { get; set; }
        public string MemoriaRam { get; set; }
        public string DiscosDuros { get; set; }
        public string TarjetasRed { get; set; }
        public string UsuarioSistema { get; set; }
        public DateTime FechaCaptura { get; set; }
        public string UserAgent { get; set; }
        public string Navegador { get; set; }
        public string SistemaOperativo { get; set; }
        public string NombreEquipo { get; set; }
        public string Fabricante { get; set; }
        public string Modelo { get; set; }
        public string NumeroSerie { get; set; }
        public string OrigenDatos { get; set; }
    }
}